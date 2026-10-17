<?php

namespace App\Actions\Moderation;

use App\Enums\Moderation\Escalation;
use App\Mail\Moderation\ContentHeldNotice;
use App\Models\ContentCheck;
use App\Models\ModerationCase;
use App\Models\Post;
use App\Support\Moderation\ContentVerdict;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Stores the verdict of the automated check and acts on it:
 * - flagged → a moderation case (source `content_check`, urgent for sexual content, threats or extremism);
 * - clearly sexual or clearly abusive Tropfen → held (hidden for everyone but the author) until a human decides, with
 *   an Art. 17 DSA notice to the author. PublishPost may already have held it when it was sent; a hold the full check
 *   does not confirm is lifted again. Nothing is deleted automatically.
 * - sexual reference to minors → the case is escalated at once (EscalateCase: quarantine, evidence, alert).
 */
class ApplyContentVerdict
{
    public function __construct(
        private readonly OpenCase $openCase,
        private readonly EscalateCase $escalate,
        private readonly SuspendAccount $suspend,
    ) {}

    public function handle(ContentCheck $check, ContentVerdict $verdict): ?ModerationCase
    {
        $heldPost = null;
        $case = DB::transaction(function () use ($check, $verdict, &$heldPost): ?ModerationCase {
            $check->update([
                'status' => 'checked', 'scores' => $verdict->scores, 'reason' => $verdict->reason ?: null,
                'source' => $verdict->source, 'flagged' => $verdict->shouldReport(), 'checked_at' => now(),
            ]);
            $post = $check->checkable;
            if (! $verdict->shouldReport()) {
                if ($post instanceof Post && $post->held_at !== null
                    && ModerationCase::where('source_type', $check->getMorphClass())->where('source_id', $check->id)->doesntExist()) {
                    $post->forceFill(['held_at' => null])->save();
                }

                return null;
            }

            if ($post instanceof Post && $verdict->shouldHold()) {
                if ($post->held_at === null) {
                    $post->forceFill(['held_at' => now()])->save();
                }
                $heldPost = $post;
            }

            return $this->openCase->handle($check);
        });

        if ($heldPost instanceof Post) {
            Mail::to($heldPost->author->email)->send(new ContentHeldNotice($heldPost, (string) $verdict->holdReason()));
            // The lock usually started when the Tropfen was sent; this covers holds found only now and links the case.
            $this->suspend->provisional($heldPost, (string) $verdict->holdReason(), $verdict->reason);
            if ($case !== null) {
                $this->suspend->attachCase($heldPost, $case);
            }
        }
        // Sexual reference to minors: escalate without waiting for a person (quarantine, evidence, alert). A human
        // still decides about consequences and any report.
        if ($case !== null && $verdict->scores['sexual_minors'] >= (float) config('oasis.moderation.report_threshold')) {
            $case = $this->escalate->handle($case, null, Escalation::ChildAbuse);
        }

        return $case;
    }
}
