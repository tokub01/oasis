<?php

namespace App\Http\Requests\Oasis;

/**
 * Validates oasis.kuendigen.store for the declaration required by § 312k BGB.
 * Reason and date depend on the selected cancellation options.
 */
class CancellationFormRequest extends OasisFormRequest
{
    /**
     * Require identification and applicable details; compare dates against the Berlin calendar day.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
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
