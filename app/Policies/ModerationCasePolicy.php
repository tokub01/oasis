<?php

namespace App\Policies;

use App\Enums\Moderation\CaseStatus;
use App\Models\ModerationCase;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/** Case permissions shared by staff HTTP endpoints, Livewire and workflow actions. */
class ModerationCasePolicy
{
    /** Staff may inspect case history after confirming 2FA. */
    public function view(User $user, ModerationCase $case): bool
    {
        return Gate::forUser($user)->allows('viewModeration');
    }

    /** Assignment only applies while the initial decision is still outstanding. */
    public function assign(User $user, ModerationCase $case): bool
    {
        return $this->decide($user, $case);
    }

    /** Prevent repeat or concurrent initial decisions on already decided cases. */
    public function decide(User $user, ModerationCase $case): bool
    {
        return $this->view($user, $case) && in_array($case->status, [CaseStatus::Open, CaseStatus::InReview], true);
    }
}
