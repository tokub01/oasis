<?php

namespace App\Actions\Moderation;

use App\Mail\Moderation\AccountReleasedNotice;
use App\Models\AccountSuspension;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;

/**
 * Ends a lock explicitly (moderation "Sperren" page or a decision). Moderators release provisional locks and locks
 * from decisions; only admins lift a permanent ban. The member is told by mail.
 */
class LiftSuspension
{
    /** @param  string  $result  released (no violation), confirmed (violation, lock ends) or overturned (complaint) */
    public function handle(AccountSuspension $suspension, ?User $actor, string $result = 'released'): void
    {
        if ($actor !== null) {
            Gate::forUser($actor)->authorize($suspension->type === AccountSuspension::BAN ? 'administrate' : 'viewModeration');
            abort_if($actor->id === $suspension->user_id, 403);
        }
        if ($suspension->ended_at !== null) {
            return;
        }
        $suspension->update(['ended_at' => now(), 'ended_by' => $actor?->id, 'result' => $result]);
        Mail::to($suspension->user->email)->send(new AccountReleasedNotice($suspension));
    }
}
