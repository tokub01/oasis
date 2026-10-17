<?php

namespace App\Actions\Moderation;

use App\Models\ChatReport;
use App\Models\ModerationCase;
use App\Models\Oasis;
use App\Models\Post;
use App\Models\Profile;
use App\Models\User;
use App\Support\Moderation\CaseSubject;
use Illuminate\Support\Facades\Storage;

/**
 * Secures what an escalated case is about, apart from the content itself: a text snapshot with the facts an
 * authority needs (who, when, where) and a copy of every image with its SHA-256 checksum, below evidence/case-<id>/ on
 * the private media disk. Nothing is sent anywhere. Kept for `oasis.moderation.evidence_days`.
 */
class SecureEvidence
{
    /** @return int number of evidence records */
    public function handle(ModerationCase $case, ?User $actor): int
    {
        $subject = CaseSubject::resolve($case);
        $purgeAfter = now()->addDays((int) config('oasis.moderation.evidence_days'));
        $base = ['case_url' => $case->subject_url, 'secured_at' => now()->toIso8601String()];
        $records = match (true) {
            $subject instanceof Post => $this->post($case, $subject),
            $subject instanceof Profile => [['kind' => 'text', 'content' => trim($subject->bio."\n".$subject->location),
                'meta' => ['type' => 'profile'] + $this->account($subject->user)]],
            $subject instanceof Oasis => [['kind' => 'text', 'content' => $subject->name."\n\n".$subject->description."\n\n".$subject->house_rules,
                'meta' => ['type' => 'oasis', 'oasis_id' => $subject->id, 'slug' => $subject->slug] + $this->account(User::find($subject->created_by))]],
            $subject instanceof ChatReport => [['kind' => 'text', 'content' => $subject->plaintext,
                'meta' => ['type' => 'chat_message', 'conversation_id' => $subject->conversation_id,
                    'sent_until' => $subject->message_expires_at?->toIso8601String()] + $this->account(User::find($subject->sender_id))]],
            default => [['kind' => 'text', 'content' => null, 'meta' => ['type' => 'unresolved']]],
        };
        foreach ($records as $record) {
            $case->evidence()->create($record + ['captured_by' => $actor?->id, 'purge_after' => $purgeAfter]
                + ['meta' => $base + $record['meta']]);
        }

        return count($records);
    }

    /**
     * Text and images of one Tropfen; the images are copied, never moved, so a wrong escalation loses nothing.
     *
     * @return list<array<string, mixed>>
     */
    private function post(ModerationCase $case, Post $post): array
    {
        $meta = ['type' => 'post', 'post_id' => $post->id, 'visibility' => $post->visibility->value, 'oasis' => $post->oasis?->slug,
            'reply_to_id' => $post->reply_to_id, 'written_at' => $post->created_at->toIso8601String()] + $this->account($post->author);
        $records = [['kind' => 'text', 'content' => $post->body, 'meta' => $meta]];
        $disk = Storage::disk(config('oasis.media_disk'));
        foreach ($post->images as $image) {
            $bytes = $disk->get($image->path);
            if ($bytes === null) {
                continue;
            }
            $path = 'evidence/case-'.$case->id.'/post-'.$post->id.'-'.$image->position.'.webp';
            $disk->put($path, $bytes);
            $records[] = ['kind' => 'image', 'path' => $path, 'sha256' => hash('sha256', $bytes), 'bytes' => strlen($bytes),
                'meta' => ['type' => 'post_image', 'post_id' => $post->id, 'position' => $image->position, 'alt' => $image->alt,
                    'width' => $image->width, 'height' => $image->height]];
        }

        return $records;
    }

    /**
     * The account behind the content, as it is at this moment (it may be deleted later).
     *
     * @return array<string, mixed>
     */
    private function account(?User $user): array
    {
        return $user === null ? ['account' => null] : ['account' => [
            'id' => $user->id, 'username' => $user->username, 'display_name' => $user->display_name, 'email' => $user->email,
            'registered_at' => $user->created_at?->toIso8601String(),
        ]];
    }
}
