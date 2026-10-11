<?php

namespace App\Actions\Moderation;

use App\Enums\Moderation\CaseStatus;
use App\Enums\Moderation\EventType;
use App\Models\ModerationCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Serializes assignment with initial decisions so the case and audit trail cannot diverge. */
class AssignCase
{
    /** Move an undecided case into review and record the staff assignment atomically. */
    public function handle(ModerationCase $case, User $actor, User $assignee): void
    {
        DB::transaction(function () use ($case, $actor, $assignee) {
            $case = ModerationCase::lockForUpdate()->findOrFail($case->id);
            Gate::forUser($actor)->authorize('assign', $case);
            $assignee = User::lockForUpdate()->findOrFail($assignee->id);
            if (! $assignee->role->isStaff()) {
                throw ValidationException::withMessages(['assigned_to' => 'Bitte wähle eine Person aus dem Moderationsteam aus.']);
            }
            $case->update(['assigned_to' => $assignee->id, 'status' => CaseStatus::InReview]);
            $case->events()->create(['actor_id' => $actor->id, 'type' => EventType::Assigned, 'data' => ['assigned_to' => $assignee->id]]);
        });
    }
}
