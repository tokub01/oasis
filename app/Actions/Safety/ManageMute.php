<?php

namespace App\Actions\Safety;

use App\Models\Mute;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Manages muting and unmuting accounts.
 *
 * Muting is unilateral: Tropfen disappear from the muter's feed, oases and replies.
 * The muted person's profile remains accessible. The muted person receives no notification.
 */
class ManageMute
{
    /**
     * Mute an account.
     *
     * @throws ValidationException
     */
    public function mute(User $muter, User $target): Mute
    {
        if ($muter->id === $target->id) {
            throw ValidationException::withMessages(['mute' => 'Du kannst dich nicht selbst stummschalten.']);
        }

        $mute = Mute::firstOrCreate([
            'user_id' => $muter->id,
            'muted_user_id' => $target->id,
        ]);

        $muter->clearSafetyCache();

        return $mute;
    }

    /**
     * Unmute an account.
     */
    public function unmute(User $muter, User $target): void
    {
        Mute::query()
            ->where('user_id', $muter->id)
            ->where('muted_user_id', $target->id)
            ->delete();

        $muter->clearSafetyCache();
    }
}
