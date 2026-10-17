<?php

namespace App\Actions\Safety;

use App\Models\Restriction;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Restricts and un-restricts accounts ("Einschränken"). Unilateral and silent: the restricted person is not told.
 * Their replies under the member's Tropfen are seen only by themselves, and they cause no hints for the member.
 */
class ManageRestriction
{
    /** @throws ValidationException when a member tries to restrict themselves */
    public function restrict(User $member, User $target): Restriction
    {
        if ($member->id === $target->id) {
            throw ValidationException::withMessages(['restrict' => 'Du kannst dich nicht selbst einschränken.']);
        }
        $restriction = Restriction::firstOrCreate(['user_id' => $member->id, 'restricted_user_id' => $target->id]);
        $member->clearSafetyCache();

        return $restriction;
    }

    public function unrestrict(User $member, User $target): void
    {
        Restriction::where('user_id', $member->id)->where('restricted_user_id', $target->id)->delete();
        $member->clearSafetyCache();
    }
}
