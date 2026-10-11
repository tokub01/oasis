<?php

namespace App\Actions\Moderation;

use App\Enums\Moderation\CaseStatus;
use App\Enums\Moderation\EventType;
use App\Models\ModerationCase;
use App\Models\ModerationComplaint;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** A second human reviewer upholds or replaces the current decision, preserving the original history. */
class ReviewComplaint
{
    /**
     * Save the result and optional superseding Art. 17 decision with all audit transitions atomically.
     *
     * @param  array<string, mixed>  $data  Validated decision or complaint review fields.
     */
    public function handle(ModerationComplaint $complaint, User $actor, array $data): void
    {
        $replacement = DB::transaction(function () use ($complaint, $actor, $data) {
            $case = ModerationCase::lockForUpdate()->findOrFail($complaint->case_id);
            $complaint = ModerationComplaint::lockForUpdate()->findOrFail($complaint->id);
            Gate::forUser($actor)->authorize('reviewComplaint', $complaint);
            $replacement = null;
            if ($data['result'] === 'overturned') {
                $replacement = $case->decisions()->create([
                    ...Arr::only($data, ['outcome', 'ground', 'rule_reference', 'facts', 'explanation']),
                    'decided_by' => $actor->id, 'automated' => false, 'decided_at' => now(),
                    'is_complaint_review' => true, 'supersedes_decision_id' => $case->latestDecision->id,
                ]);
                $case->events()->create(['actor_id' => $actor->id, 'type' => EventType::Decided,
                    'data' => ['decision_id' => $replacement->id, 'supersedes_decision_id' => $replacement->supersedes_decision_id,
                        'outcome' => $replacement->outcome->value, 'ground' => $replacement->ground->value]]);
            }
            $complaint->update(['status' => $data['result'], 'reviewed_by' => $actor->id,
                'review_explanation' => $data['explanation'], 'reviewed_at' => now()]);
            // Other parties may have independent complaints about the same original decision.
            $case->update(['status' => $case->complaints()->where('status', 'open')->exists()
                ? CaseStatus::ComplaintOpen : CaseStatus::Closed]);
            $case->events()->create(['actor_id' => $actor->id, 'type' => EventType::ComplaintDecided,
                'data' => ['complaint_id' => $complaint->id, 'result' => $data['result']]]);

            return $replacement;
        });
        DB::afterCommit(function () use ($replacement, $complaint) {
            if ($replacement) {
                app(NotifyDecision::class)->handle($replacement);
            }
            app(NotifyComplaint::class)->handle($complaint->fresh());
        });
    }
}
