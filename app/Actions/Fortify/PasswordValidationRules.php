<?php

namespace App\Actions\Fortify;

use Illuminate\Validation\Rules\Password;

/** Shared password policy for registration, changes and resets through Fortify. */
trait PasswordValidationRules
{
    /**
     * Keep the ten-character floor even when production defaults are stricter.
     *
     * @return list<string|Password>
     */
    protected function passwordRules(): array
    {
        return ['required', 'string', 'min:10', Password::defaults(), 'confirmed'];
    }
}
