<?php

namespace App\Actions\Moderation;

use App\Enums\Moderation\EventType;
use App\Models\ModerationCase;
use App\Models\ModerationEvidence;
use App\Models\Post;
use App\Models\User;
use App\Support\Moderation\CaseSubject;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * What happens after an escalation, each step written to the case history: recording a report a person made to an
 * authority (Oasis sends nothing itself), extending or ending the evidence period, and lifting a quarantine.
 * Callers check the permissions: staff record reports, admins handle evidence and quarantine.
 */
class ManageEscalation
{
    /** Note when and under which reference staff reported the case to an authority. */
    public function recordReport(ModerationCase $case, User $actor, string $reference): void
    {
        $reference = trim($reference);
        if ($case->escalation === null) {
            throw ValidationException::withMessages(['reference' => 'Nur eskalierte Fälle haben eine Behördenmeldung.']);
        }
        if (mb_strlen($reference) < 3 || mb_strlen($reference) > 160) {
            throw ValidationException::withMessages(['reference' => 'Gib Behörde und Aktenzeichen an (3 bis 160 Zeichen).']);
        }
        DB::transaction(function () use ($case, $actor, $reference): void {
            $case->forceFill(['authority_reported_at' => now(), 'authority_reference' => $reference])->save();
            $case->events()->create(['actor_id' => $actor->id, 'type' => EventType::AuthorityReported, 'data' => ['reference' => $reference]]);
        });
    }

    /** Keep the evidence longer, e.g. when an authority asks for it: the period starts again from today. */
    public function extendEvidence(ModerationCase $case, User $actor): void
    {
        $until = now()->addDays((int) config('oasis.moderation.evidence_days'));
        DB::transaction(function () use ($case, $actor, $until): void {
            $case->evidence()->update(['purge_after' => $until]);
            $case->events()->create(['actor_id' => $actor->id, 'type' => EventType::EvidenceExtended, 'data' => ['until' => $until->toDateString()]]);
        });
    }

    /** Delete the evidence and its files before the period ends, e.g. once the authority has it. */
    public function deleteEvidence(ModerationCase $case, User $actor): void
    {
        DB::transaction(function () use ($case, $actor): void {
            $case->evidence()->get()->each(function (ModerationEvidence $evidence): void {
                $evidence->deleteFile();
                $evidence->delete();
            });
            $case->events()->create(['actor_id' => $actor->id, 'type' => EventType::EvidenceDeleted, 'data' => []]);
        });
    }

    /** Serve the Tropfen's images again (it stays held until a decision releases it). */
    public function liftQuarantine(ModerationCase $case, User $actor): void
    {
        $post = CaseSubject::resolve($case);
        if (! $post instanceof Post || $post->quarantined_at === null) {
            throw ValidationException::withMessages(['quarantine' => 'Dieser Fall hat keinen Tropfen in Quarantäne.']);
        }
        DB::transaction(function () use ($case, $actor, $post): void {
            $post->forceFill(['quarantined_at' => null])->save();
            $case->events()->create(['actor_id' => $actor->id, 'type' => EventType::QuarantineLifted, 'data' => ['post_id' => $post->id]]);
        });
    }
}
