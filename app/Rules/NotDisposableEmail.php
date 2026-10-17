<?php

namespace App\Rules;

use App\Support\Security\BotProtection;
use App\Support\Security\DisposableEmail;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Refuses throwaway addresses for accounts and the waitlist (not for reports, cancellations or withdrawals). */
class NotDisposableEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && DisposableEmail::isDisposable($value)) {
            BotProtection::count('disposable_email');
            $fail('Bitte nutze eine dauerhafte E-Mail-Adresse – Wegwerf-Adressen können wir nicht annehmen.');
        }
    }
}
