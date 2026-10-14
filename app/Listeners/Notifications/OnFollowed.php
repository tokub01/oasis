<?php

namespace App\Listeners\Notifications;

use App\Actions\Notifications\SendHint;
use App\Enums\Notifications\NoticeKind;
use App\Events\Network\Followed;
use App\Models\Follow;
use App\Models\User;

/** Notify people about a new Karawane member; oasis follows never create hints. */
class OnFollowed
{
    /** The follow row identifies the event, allowing a later genuine re-follow. */
    public function process(Followed $event): void
    {
        if (! $event->target instanceof User) {
            return;
        }
        $follow = Follow::where('follower_id', $event->follower->id)
            ->where('followable_type', $event->target->getMorphClass())
            ->where('followable_id', $event->target->id)->first();
        if ($follow !== null) {
            app(SendHint::class)->handle($event->target, $event->follower, NoticeKind::Follow, 'follow:'.$follow->id);
        }
    }
}
