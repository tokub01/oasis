<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/** Applies the shared password policy after Fortify verifies a reset token. */
class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate the new password; the User cast hashes it on assignment.
     *
     * @param  array<string, mixed>  $input  Submitted password and confirmation.
     *
     * @throws ValidationException
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, ['password' => $this->passwordRules()])->validate();
        $user->forceFill(['password' => $input['password']])->save();
    }
}
