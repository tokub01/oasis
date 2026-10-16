<?php

namespace App\Livewire\Forms\Oasis;

/**
 * The § 312k BGB cancellation declaration. The reason applies only to an extraordinary cancellation and the date
 * only to "Zu einem bestimmten Datum"; inapplicable values are excluded from the validated data and never stored.
 */
class CancellationForm extends PublicForm
{
    public string $name = '';

    public string $email = '';

    public string $username = '';

    /** The only contract that can be cancelled; validated so a manipulated value is rejected. */
    public string $contract = 'Oasis-Mitgliedschaft';

    /** "ordentlich" or "ausserordentlich". */
    public string $type = 'ordentlich';

    public string $reason = '';

    /** "naechst" (next possible date) or "datum" (the date below). */
    public string $timing = 'naechst';

    /** Y-m-d, compared against the Berlin calendar day. */
    public string $effective_date = '';

    /** @return array<string, list<string>> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:254'],
            'username' => ['nullable', 'string', 'max:255'],
            'contract' => ['required', 'in:Oasis-Mitgliedschaft'],
            'type' => ['required', 'in:ordentlich,ausserordentlich'],
            'reason' => ['exclude_unless:type,ausserordentlich', 'required_if:type,ausserordentlich', 'string', 'max:10000'],
            'timing' => ['required', 'in:naechst,datum'],
            'effective_date' => [
                'exclude_unless:timing,datum',
                'required_if:timing,datum',
                'date_format:Y-m-d',
                'after_or_equal:'.now('Europe/Berlin')->toDateString(),
            ],
        ] + $this->spamRules();
    }
}
