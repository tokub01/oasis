<?php

namespace App\Http\Requests\Oasis;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

abstract class OasisFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => Str::lower(trim($email))]);
        }
    }

    /**
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
     * @return array<string, list<string>>
     */
    protected function spamRules(): array
    {
        return ['website' => ['nullable', 'string', 'max:0']];
    }
}
