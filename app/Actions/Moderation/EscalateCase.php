<?php

namespace App\Actions\Moderation;

use App\Enums\Moderation\Escalation;
use App\Enums\Moderation\EventType;
use App\Enums\Moderation\Priority;
use App\Mail\Moderation\EscalationAlert;
use App\Models\ModerationCase;
use App\Models\Post;
use App\Models\User;
use App\Support\Moderation\CaseSubject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Escalates a case (docs/oasis-moderation.md, "Eskalation"): marks it, puts it on top of the queue, secures the
 * evidence (SecureEvidence) and, for depictions of child abuse, quarantines the Tropfen at once – hidden for everyone
 * and its images served to nobody, staff included. The moderation inbox gets a short alert without any content.
 * Oasis contacts no authority; staff do that by hand following the checklist. A case is escalated only once.
 */
class EscalateCase
{
    public function __construct(private readonly SecureEvidence $evidence) {}

    /** @param  User|null  $actor  null for the automated check */
    public function handle(ModerationCase $case, ?User $actor, Escalation $type): ModerationCase
    {
        $escalated = DB::transaction(function () use ($case, $actor, $type): bool {
            $case = ModerationCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
            if ($case->escalation !== null) {
                return false;
            }
            $case->forceFill(['escalation' => $type, 'escalated_at' => now(), 'escalated_by' => $actor?->id,
                'priority' => Priority::Urgent])->save();
            $count = $this->evidence->handle($case, $actor);
            $subject = CaseSubject::resolve($case);
            if ($type === Escalation::ChildAbuse && $subject instanceof Post) {
                $subject->forceFill(['held_at' => $subject->held_at ?? now(), 'quarantined_at' => now()])->save();
            }
            $case->events()->create(['actor_id' => $actor?->id, 'type' => EventType::Escalated,
                'data' => ['escalation' => $type->value, 'evidence' => $count, 'automatic' => $actor === null]]);

            return true;
        });

        $case->refresh();
        if ($escalated) {
            Mail::to(config('oasis.mail.moderation'))->send(new EscalationAlert($case));
        }

        return $case;
    }
}
