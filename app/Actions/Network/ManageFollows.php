<?php

namespace App\Actions\Network;

use App\Enums\Network\OasisRole;
use App\Events\Network\Followed;
use App\Models\Follow;
use App\Models\Oasis;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Follow and unfollow people and oases. Following an oasis also makes you a member of it (you may post there);
 * unfollowing ends the membership. The last host cannot leave, so no oasis is left without a host.
 */
class ManageFollows
{
    /** Idempotent: following twice keeps one follow. */
    public function follow(User $follower, User|Oasis $target): void
    {
        if ($target instanceof User && $target->is($follower)) {
            throw ValidationException::withMessages(['follow' => 'Dir selbst kannst du nicht folgen.']);
        }
        if ($target instanceof User && $follower->isBlockedWith($target)) {
            throw ValidationException::withMessages(['follow' => 'Diese Person kann nicht gefolgt werden.']);
        }

        DB::transaction(function () use ($follower, $target): void {
            $follow = Follow::firstOrCreate([
                'follower_id' => $follower->id,
                'followable_type' => $target->getMorphClass(),
                'followable_id' => $target->getKey(),
            ]);
            if ($follow->wasRecentlyCreated) {
                event(new Followed($follower, $target));
            }
            if ($target instanceof Oasis && ! $target->isMember($follower)) {
                $target->members()->attach($follower->id, ['role' => OasisRole::Member->value, 'joined_at' => now()]);
            }
        });
    }

    public function unfollow(User $follower, User|Oasis $target): void
    {
        DB::transaction(function () use ($follower, $target): void {
            if ($target instanceof Oasis) {
                if ($target->isHost($follower) && $target->hosts()->count() === 1) {
                    throw ValidationException::withMessages(['follow' => 'Du bist die einzige Gastgeber:in. Übergib die Oase zuerst.']);
                }
                $target->members()->detach($follower->id);
            }
            Follow::where('follower_id', $follower->id)
                ->where('followable_type', $target->getMorphClass())
                ->where('followable_id', $target->getKey())
                ->delete();
        });
    }
}
