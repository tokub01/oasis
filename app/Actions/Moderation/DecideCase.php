<?php

namespace App\Actions\Moderation;

use App\Enums\Moderation\CaseStatus;
use App\Enums\Moderation\EventType;
use App\Models\ModerationCase;
use App\Models\ModerationDecision;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Records the initial human statement of reasons, then delivers recipient-specific notices. */
class DecideCase
{
    /**
     * Lock the case and save the decision, state and audit entry in one transaction.
     *
     * @param  array<string, mixed>  $data  Validated decision or complaint review fields.
     */
    public function handle(ModerationCase $case, User $actor, array $data): ModerationDecision
    {
        $decision = DB::transaction(function () use ($case, $actor, $data) {
            $case = ModerationCase::lockForUpdate()->findOrFail($case->id);
            Gate::forUser($actor)->authorize('decide', $case);
            $decision = $case->decisions()->create([
                ...Arr::only($data, ['outcome', 'ground', 'rule_reference', 'facts', 'explanation']),
                'decided_by' => $actor->id, 'automated' => false, 'decided_at' => now(),
            ]);
            $case->update(['status' => CaseStatus::Decided]);
            $case->events()->create(['actor_id' => $actor->id, 'type' => EventType::Decided,
                'data' => ['decision_id' => $decision->id, 'outcome' => $decision->outcome->value, 'ground' => $decision->ground->value]]);

            return $decision;
        });
        app(ReleaseHeldContent::class)->handle($decision);
        // External mail cannot roll back. Retain committed decisions for retry if transport fails.
        DB::afterCommit(fn () => app(NotifyDecision::class)->handle($decision));

        return $decision;
    }
}
