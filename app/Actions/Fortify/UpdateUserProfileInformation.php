<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

/** Updates display name and email from settings; usernames and birth dates stay fixed. */
class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Invalidate verification when the email changes and send a fresh verification link.
     *
     * @param  array<string, mixed>  $input  Submitted display name and email.
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        if (isset($input['email']) && is_string($input['email'])) {
            $input['email'] = Str::lower($input['email']);
        }

        Validator::make($input, [
            'display_name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ])->validateWithBag('updateProfileInformation');

        $emailChanged = $input['email'] !== $user->email;
        $user->forceFill([
            'display_name' => $input['display_name'],
            'email' => $input['email'],
            'email_verified_at' => $emailChanged ? null : $user->email_verified_at,
        ])->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }
    }
}
