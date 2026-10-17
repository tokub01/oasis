<?php

namespace App\Support\Network;

use App\Models\Block;
use App\Models\Follow;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/** Parses bounded @usernames for notifications and the network rich-text component. */
final class Mentions
{
    /** Shared token rule excludes emails and partial matches of too-long usernames. */
    public const PATTERN = '/(?<![\p{L}\p{N}_@.\-+])@([a-z0-9_]{3,30})(?![\p{L}\p{N}_@])/iu';

    /**
     * Return distinct usernames, lowercase and in order of first appearance.
     *
     * @return list<string>
     */
    public static function usernames(string $text): array
    {
        preg_match_all(self::PATTERN, $text, $matches);

        return array_values(array_unique(array_map('strtolower', $matches[1])));
    }

    /**
     * Resolve names in one query per batch, including misses; cache only inside this request.
     *
     * @param  list<string>  $names
     * @return array<string, User|null>
     */
    private static function resolve(array $names): array
    {
        /** @var array<string, User|null> $known */
        $known = request()->attributes->get(self::class.'.users', []);
        $missing = array_values(array_diff($names, array_keys($known)));
        if ($missing !== []) {
            foreach ($missing as $name) {
                $known[$name] = null;
            }
            foreach (User::whereIn('username', $missing)->with('privacySetting')->get() as $user) {
                $known[strtolower($user->username)] = $user;
            }
            request()->attributes->set(self::class.'.users', $known);
        }

        return $known;
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    public static function existing(array $names): array
    {
        $names = array_values(array_unique(array_map('strtolower', $names)));
        $known = self::resolve($names);

        return array_values(array_filter($names, fn (string $name) => $known[$name] !== null));
    }

    /**
     * The author, rather than the current reader, determines whether a mention becomes a link.
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    public static function linkable(array $names, ?User $author): array
    {
        if ($author === null) {
            return [];
        }
        $names = self::existing($names);
        /** @var array<int, array<string, bool>> $links */
        $links = request()->attributes->get(self::class.'.links', []);
        $missing = array_values(array_diff($names, array_keys($links[$author->id] ?? [])));
        if ($missing !== []) {
            self::prepareLinks($missing, [$author]);
            $links = request()->attributes->get(self::class.'.links', []);
        }

        return array_values(array_filter($names, fn (string $name) => $links[$author->id][$name]));
    }

    /**
     * Bulk follow/block edges keep mention rendering independent of the number of cards.
     *
     * @param  list<string>  $names
     * @param  list<User>  $authors
     */
    private static function prepareLinks(array $names, array $authors): void
    {
        $known = self::resolve($names);
        $users = new Collection(array_values(array_filter(array_intersect_key($known, array_flip($names)))));
        if ($users->isEmpty() || $authors === []) {
            return;
        }
        $authorIds = array_map(fn (User $author) => $author->id, $authors);
        $recipientIds = $users->modelKeys();
        $follows = Follow::whereIn('follower_id', $recipientIds)->where('followable_type', (new User)->getMorphClass())
            ->whereIn('followable_id', $authorIds)->get()->groupBy('follower_id');
        $blocks = Block::where(fn ($query) => $query->whereIn('user_id', $authorIds)->whereIn('blocked_user_id', $recipientIds))
            ->orWhere(fn ($query) => $query->whereIn('blocked_user_id', $authorIds)->whereIn('user_id', $recipientIds))->get();
        /** @var array<int, array<string, bool>> $links */
        $links = request()->attributes->get(self::class.'.links', []);
        foreach ($users as $user) {
            $followedIds = $follows->get($user->id, new Collection)->pluck('followable_id')
                ->map(fn ($id) => (int) $id)->values()->all();
            $settings = $user->privacy()->withKnownFollows($authorIds, array_values($followedIds));
            foreach ($authors as $author) {
                $blocked = $blocks->contains(fn (Block $block) => ($block->user_id === $author->id && $block->blocked_user_id === $user->id)
                    || ($block->blocked_user_id === $author->id && $block->user_id === $user->id));
                $links[$author->id][strtolower($user->username)] = ! $blocked && $settings->allows('mentions_audience', $author);
            }
        }
        request()->attributes->set(self::class.'.links', $links);
    }

    /**
     * Batch all card text before rendering, including quotes and bundled replies.
     *
     * @param  iterable<Post>  $posts
     */
    public static function preloadPosts(iterable $posts): void
    {
        $names = [];
        $authors = [];
        foreach ($posts as $post) {
            $names = [...$names, ...self::usernames($post->body)];
            $authors[$post->user_id] = $post->author;
            if ($post->relationLoaded('carried') && $post->carried !== null) {
                $names = [...$names, ...self::usernames($post->carried->body)];
                $authors[$post->carried->user_id] = $post->carried->author;
            }
            if ($post->relationLoaded('conversationReplies')) {
                foreach ($post->conversationReplies as $reply) {
                    $names = [...$names, ...self::usernames($reply->body)];
                    $authors[$reply->user_id] = $reply->author;
                }
            }
        }
        self::prepareLinks(array_values(array_unique($names)), array_values($authors));
    }
}
