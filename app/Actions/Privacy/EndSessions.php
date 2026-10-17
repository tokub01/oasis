<?php

namespace App\Actions\Privacy;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Signs a member out on other devices ("Wo du angemeldet bist"). Removes the stored sessions and renews the
 * remember-me token, so no device can sign itself in again with an old "Angemeldet bleiben" cookie. The current
 * session is never touched. Requires the database session driver (config/session.php).
 */
class EndSessions
{
    /** End one other session of the member; returns false when it is not theirs, already gone or the current one. */
    public function one(User $member, string $sessionId, string $currentId): bool
    {
        if ($sessionId === $currentId) {
            return false;
        }
        $ended = DB::table('sessions')->where('user_id', $member->id)->where('id', $sessionId)->delete() > 0;
        if ($ended) {
            $this->renewRememberToken($member);
        }

        return $ended;
    }

    /** End all sessions of the member except the current one; returns how many ended. */
    public function others(User $member, string $currentId): int
    {
        $ended = DB::table('sessions')->where('user_id', $member->id)->where('id', '!=', $currentId)->delete();
        $this->renewRememberToken($member);

        return $ended;
    }

    private function renewRememberToken(User $member): void
    {
        $member->forceFill(['remember_token' => Str::random(60)])->save();
    }
}
