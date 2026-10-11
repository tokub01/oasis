<?php

namespace App\Policies;

use App\Enums\Moderation\ComplaintStatus;
use App\Models\ModerationComplaint;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/** Enforces the AGB § 12 second-person rule even when the original decider is an admin. */
class ModerationComplaintPolicy
{
    /** Staff can read complaints, including complaints about their own decisions. */
    public function view(User $user, ModerationComplaint $complaint): bool
    {
        return Gate::forUser($user)->allows('viewModeration');
    }

    /** Only a different person can decide a currently open complaint about the original decision. */
    public function reviewComplaint(User $user, ModerationComplaint $complaint): bool
    {
        return $this->view($user, $complaint)
            && $complaint->status === ComplaintStatus::Open
            && $complaint->decision->decided_by !== $user->id;
    }
}
