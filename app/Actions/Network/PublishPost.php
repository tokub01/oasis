<?php

namespace App\Actions\Network;

use App\Enums\Network\PostKind;
use App\Enums\Network\Visibility;
use App\Events\Network\PostPublished;
use App\Models\Oasis;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Publishes a Tropfen: a new post, a reply or a carried post ("weitertragen" with own words), optionally in an oasis
 * and with images. Authorization and input validation happen before (PostInput / PostPolicy).
 *
 * Visibility rules: a Tropfen in a members-only oasis, a reply to or a carry of a members-only Tropfen is always
 * members-only, so nothing becomes more public than its context.
 */
class PublishPost
{
    public function __construct(private readonly StorePostImage $images) {}

    /**
     * @param  array{body: string, visibility: string, kind?: string|PostKind|null, oasis?: Oasis|null, reply_to?: Post|null, carried?: Post|null}  $input
     * @param  list<array{file: UploadedFile, alt: string}>  $images
     */
    public function handle(User $author, array $input, array $images = []): Post
    {
        $context = $input['reply_to'] ?? null;
        $oasis = $input['oasis'] ?? $context?->oasis;
        $visibility = Visibility::from($input['visibility']);
        foreach ([$oasis?->visibility, $context?->visibility, ($input['carried'] ?? null)?->visibility] as $restriction) {
            if ($restriction === Visibility::Members) {
                $visibility = Visibility::Members;
            }
        }

        $kind = match ($input['kind'] ?? null) {
            PostKind::Question, 'question' => PostKind::Question,
            default => PostKind::Post,
        };

        // Replies and carried posts are always regular posts.
        if ($context !== null || ($input['carried'] ?? null) !== null) {
            $kind = PostKind::Post;
        }

        $post = DB::transaction(fn () => Post::create([
            'user_id' => $author->id,
            'oasis_id' => $oasis?->id,
            'reply_to_id' => $context?->id,
            'carried_post_id' => ($input['carried'] ?? null)?->id,
            'body' => $input['body'],
            'visibility' => $visibility,
            'kind' => $kind,
        ]));

        try {
            foreach ($images as $position => $image) {
                $this->images->handle($post, $image['file'], $image['alt'], $position);
            }
        } catch (Throwable $failure) {
            // No half-published Tropfen: remove the post and any stored files, then report the error.
            Storage::disk(config('oasis.media_disk'))->deleteDirectory('network/posts/'.$post->id);
            $post->delete();
            throw $failure;
        }

        event(new PostPublished($post));

        return $post;
    }
}
