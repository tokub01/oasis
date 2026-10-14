<?php

namespace App\Events\Network;

use App\Models\Oasis;
use App\Models\User;

/** Persisted network change; consumed by the notification listeners. */
final class Followed
{
    /** Carry the saved models; dispatch only after successful publication/follow creation. */
    public function __construct(public readonly User $follower, public readonly User|Oasis $target) {}
}
