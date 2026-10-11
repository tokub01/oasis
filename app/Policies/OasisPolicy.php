<?php

namespace App\Policies;

use App\Models\Oasis;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/** Oasis permissions: reading by visibility, creating with participation, running by hosts. */
class OasisPolicy
{
    public function view(?User $user, Oasis $oasis): bool
    {
        return $oasis->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail() && Gate::forUser($user)->allows('participate');
    }

    /** Posting into an oasis requires being one of its members (= following it). */
    public function post(User $user, Oasis $oasis): bool
    {
        return $this->create($user) && $oasis->isMember($user);
    }

    /** Edit texts, appoint hosts, see the host statistics. */
    public function host(User $user, Oasis $oasis): bool
    {
        return $oasis->isHost($user);
    }
}
