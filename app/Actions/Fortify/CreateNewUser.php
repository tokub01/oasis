<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Rules\NotDisposableEmail;
use App\Support\Security\BotProtection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/** Validates the Oasis sign-up form (incl. bot traps and throwaway addresses) and starts the trial through Fortify. */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate consent and the 16-year age floor before storing an unverified account.
     *
     * @param  array<string, mixed>  $input  Submitted registration fields.
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        if (BotProtection::check($input[BotProtection::FIELD] ?? null, $input[BotProtection::TOKEN] ?? null) !== null) {
            throw ValidationException::withMessages(['form' => 'Das hat nicht geklappt. Bitte prüfe deine Angaben und schick das Formular noch einmal ab.']);
        }

        // Normalize only strings so malformed requests still produce validation errors.
        foreach (['username', 'email'] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $input[$field] = Str::lower($input[$field]);
            }
        }

        Validator::make($input, [
            'display_name' => ['required', 'string', 'max:50'],
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('users'), Rule::notIn(['admin', 'administrator', 'oasis', 'support', 'moderation',
                    'moderator', 'api', 'app', 'www', 'help', 'hilfe', 'root', 'system'])],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users'), new NotDisposableEmail],
            'birth_date' => ['required', 'date_format:Y-m-d',
                'before_or_equal:'.now('Europe/Berlin')->subYears(16)->toDateString()],
            'password' => $this->passwordRules(),
            'terms' => ['accepted'],
        ], [
            'birth_date.before_or_equal' => 'Du musst mindestens 16 Jahre alt sein, um Oasis zu nutzen.',
            'username.not_in' => 'Dieser Benutzername ist reserviert. Bitte wähle einen anderen.',
            'username.regex' => 'Der Benutzername darf nur Buchstaben von a bis z, Zahlen und Unterstriche enthalten.',
            'terms.accepted' => 'Bitte akzeptiere die Nutzungsbedingungen.',
        ])->validate();

        return User::create([
            'display_name' => $input['display_name'],
            'username' => $input['username'],
            'email' => $input['email'],
            'birth_date' => $input['birth_date'],
            'password' => $input['password'],
            'terms_accepted_at' => now(),
            'trial_ends_at' => now()->addDays(config('oasis.trial_days')),
        ]);
    }
}
