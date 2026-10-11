<?php

namespace App\Actions\Moderation;

use App\Enums\Moderation\CaseStatus;
use App\Enums\Moderation\EventType;
use App\Models\ModerationCase;
use App\Models\ModerationComplaint;
use App\Models\ModerationDecision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Records the party's complaint within the promised six-month window, including guest reporters. */
class SubmitComplaint
{
    /** Resolve duplicates under the case lock and atomically open the complaint with its audit event. */
    public function handle(ModerationDecision $decision, string $email, string $reason, ?User $actor = null): ModerationComplaint
    {
        return DB::transaction(function () use ($decision, $email, $reason, $actor) {
            $case = ModerationCase::lockForUpdate()->findOrFail($decision->case_id);
            $decision = ModerationDecision::findOrFail($decision->id);
            Gate::forUser($actor)->authorize('submitComplaint', [$decision, $email]);
            if ($decision->complaints()->where('submitter_email', $email)->exists()) {
                throw ValidationException::withMessages(['reason' => 'Deine Beschwerde zu dieser Entscheidung wurde bereits eingereicht.']);
            }
            $complaint = $case->complaints()->create([
                'decision_id' => $decision->id, 'submitter_email' => $email,
                'submitted_by' => $actor?->email === $email ? $actor->id : null,
                'reason' => $reason, 'status' => 'open', 'submitted_at' => now(),
            ]);
            $case->update(['status' => CaseStatus::ComplaintOpen]);
            $case->events()->create(['actor_id' => $complaint->submitted_by, 'type' => EventType::ComplaintReceived,
                'data' => ['complaint_id' => $complaint->id, 'decision_id' => $decision->id]]);

            return $complaint;
        });
    }
}
