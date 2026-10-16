<?php

namespace App\Livewire\Forms\Oasis;

use Illuminate\Support\Str;
use Livewire\Form;

/**
 * Shared validation policy for the five public Oasis forms (founding waitlist, cancellation, withdrawal,
 * content notice, organisation enquiry): anonymous use, a honeypot, canonical e-mail addresses and German messages.
 */
abstract class PublicForm extends Form
{
    /** Honeypot: visually hidden and out of the tab order; people leave it empty, bots tend to fill it. */
    public string $website = '';

    /**
     * The form's own rules; {@see spamRules()} is added by every form.
     *
     * @return array<string, list<string>>
     */
    abstract protected function rules(): array;

    /**
     * Validate after normalising the e-mail address, so differently cased input cannot create duplicate entries.
     * Empty optional fields come back as null, as the web middleware ConvertEmptyStringsToNull did for plain forms.
     *
     * @return array<string, mixed> only the validated fields
     */
    public function validated(): array
    {
        if (property_exists($this, 'email')) {
            $this->email = Str::lower(trim($this->email));
        }

        return array_map(fn (mixed $value) => $value === '' ? null : $value, $this->validate());
    }

    /**
     * German feedback for the public website.
     *
     * @return array<string, string>
     */
    protected function messages(): array
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
     * Rejects a filled honeypot.
     *
     * @return array<string, list<string>>
     */
    protected function spamRules(): array
    {
        return ['website' => ['nullable', 'string', 'max:0']];
    }
}
