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
 * - flagged → a moderation case (source `content_check`, urgent for sexual content or threats);
 * - clearly sexual Tropfen → held (hidden for everyone but the author) until a human decides, with an Art. 17 DSA
 *   notice to the author. Nothing is deleted automatically.
 * - sexual reference to minors → the case is escalated at once (EscalateCase: quarantine, evidence, alert).
 */
class ApplyContentVerdict
{
    public function __construct(private readonly OpenCase $openCase, private readonly EscalateCase $escalate) {}

    public function handle(ContentCheck $check, ContentVerdict $verdict): ?ModerationCase
    {
        $heldPost = null;
        $case = DB::transaction(function () use ($check, $verdict, &$heldPost): ?ModerationCase {
            $check->update([
                'status' => 'checked', 'scores' => $verdict->scores, 'reason' => $verdict->reason ?: null,
                'source' => $verdict->source, 'flagged' => $verdict->shouldReport(), 'checked_at' => now(),
            ]);
            if (! $verdict->shouldReport()) {
                return null;
            }

            $post = $check->checkable;
            if ($post instanceof Post && $verdict->shouldHold() && $post->held_at === null) {
                $post->forceFill(['held_at' => now()])->save();
                $heldPost = $post;
            }

            return $this->openCase->handle($check);
        });

        if ($heldPost instanceof Post) {
            Mail::to($heldPost->author->email)->send(new ContentHeldNotice($heldPost));
        }
        // Sexual reference to minors: escalate without waiting for a person (quarantine, evidence, alert). A human
        // still decides about consequences and any report.
        if ($case !== null && $verdict->scores['sexual_minors'] >= (float) config('oasis.moderation.report_threshold')) {
            $case = $this->escalate->handle($case, null, Escalation::ChildAbuse);
        }

        return $case;
    }
}
