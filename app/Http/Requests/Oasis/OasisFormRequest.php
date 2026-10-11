<?php

namespace App\Http\Requests\Oasis;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Shared validation policy for the five public Oasis forms.
 * Provides anonymous access, canonical email addresses, German messages and a honeypot.
 */
abstract class OasisFormRequest extends FormRequest
{
    /**
     * Allow anonymous submission; public statutory forms must not require an account.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize addresses before lookup so differently cased input cannot create duplicate entries.
     */
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => Str::lower(trim($email))]);
        }
    }

    /**
     * Keep validation feedback understandable on the German public website.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'Bitte fülle dieses Feld aus.',
            'required_if' => 'Bitte fülle dieses Feld für deine Auswahl aus.',
            'email' => 'Bitte gib eine gültige E-Mail-Adresse ein.',
            'accepted' => 'Bitte bestätige diese Angabe.',
            'in' => 'Bitte wähle eine gültige Option.',
            'date_format' => 'Bitte gib ein gültiges Datum ein.',
            'effective_date.after_or_equal' => 'Bitte wähle heute oder ein Datum in der Zukunft.',
            'url' => 'Bitte gib einen gültigen Link mit https:// oder http:// ein.',
            'integer' => 'Bitte gib eine ganze Zahl ein.',
            'min' => 'Bitte gib mindestens :min an.',
            'max' => 'Bitte verwende höchstens :max Zeichen beziehungsweise einen Wert bis :max.',
            'string' => 'Bitte gib einen Text ein.',
            'website.max' => 'Das Formular konnte nicht verarbeitet werden. Bitte versuche es erneut.',
        ];
    }

    /**
     * Reject bots that fill the hidden website field while accepting an omitted or empty field.
     *
     * @return array<string, list<string>>
     */
    protected function spamRules(): array
    {
        return ['website' => ['nullable', 'string', 'max:0']];
    }
}
