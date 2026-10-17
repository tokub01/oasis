<?php

namespace App\Actions\Network;

use App\Actions\Moderation\SuspendAccount;
use App\Enums\Network\PostKind;
use App\Enums\Network\Visibility;
use App\Events\Network\PostPublished;
use App\Models\ContentCheck;
use App\Models\Oasis;
use App\Models\Post;
use App\Models\User;
use App\Support\Moderation\ContentAssessor;
use App\Support\Security\AccountLimits;
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
    public function __construct(
        private readonly StorePostImage $images,
        private readonly ContentAssessor $assessor,
        private readonly SuspendAccount $suspend,
    ) {}

    /**
     * @param  array{body: string, visibility: string, kind?: string|PostKind|null, oasis?: Oasis|null, reply_to?: Post|null, carried?: Post|null}  $input
     * @param  list<array{file: UploadedFile, alt: string}>  $images
     */
    public function handle(User $author, array $input, array $images = []): Post
    {
        // Bot protection (docs/oasis-bot-protection.md): duplicates, links and mentions of new accounts, hourly budget.
        AccountLimits::checkText($author, (string) $input['body']);
        AccountLimits::hit($author, 'posts', 'body');
        $context = $input['reply_to'] ?? null;
        $oasis = $input['oasis'] ?? $context?->oasis;
        $visibility = Visibility::from($input['visibility']);
        foreach ([$oasis?->visibility, $context?->visibility, ($input['carried'] ?? null)?->visibility] as $restriction) {
            if ($restriction === Visibility::Members) {
                $visibility = Visibility::Members;
            }
        }
        // Members under 18 write for members only (docs/oasis-privacy.md, "Unter 18").
        if ($author->isMinor()) {
            $visibility = Visibility::Members;
        }

        $kind = match ($input['kind'] ?? null) {
            PostKind::Question, 'question' => PostKind::Question,
            default => PostKind::Post,
        };

        // Replies and carried posts are always regular posts.
        if ($context !== null || ($input['carried'] ?? null) !== null) {
            $kind = PostKind::Post;
        }

        // Clearly abusive or sexual: held from the first moment, so nobody else ever sees it before a human reviewed it
        // (docs/oasis-auto-moderation.md). The composer already asked for this verdict, so it usually comes from the
        // cache; without the language model the word list answers, which never holds.
        $verdict = $this->assessor->assess((string) $input['body']);

        $post = DB::transaction(function () use ($author, $oasis, $context, $input, $visibility, $kind, $verdict) {
            $post = new Post([
                'user_id' => $author->id,
                'oasis_id' => $oasis?->id,
                'reply_to_id' => $context?->id,
                'carried_post_id' => ($input['carried'] ?? null)?->id,
                'body' => $input['body'],
                'visibility' => $visibility,
                'kind' => $kind,
            ]);
            $post->forceFill(['held_at' => $verdict->shouldHold() && ! $author->isSuperAdmin() ? now() : null])->save();
            if ($post->held_at !== null) {
                // The background check (PostPublished → CheckContent) confirms it, opens the case and sends the notice.
                ContentCheck::create(['checkable_type' => $post->getMorphClass(), 'checkable_id' => $post->id, 'status' => 'pending',
                    'scores' => $verdict->scores, 'reason' => $verdict->reason ?: null, 'source' => $verdict->source, 'flagged' => true]);
            }

            return $post;
        });

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

        AccountLimits::remember($author, $post->body);
        // Held → the account is locked at once until a person in the moderation team decides (docs/oasis-moderation.md).
        if ($post->held_at !== null) {
            $this->suspend->provisional($post, (string) $verdict->holdReason(), $verdict->reason);
        }
        event(new PostPublished($post));

        return $post;
    }
}
