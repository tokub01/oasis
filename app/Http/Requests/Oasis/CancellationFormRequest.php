<?php

namespace App\Http\Requests\Oasis;

class CancellationFormRequest extends OasisFormRequest
{
    /**
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
            'effective_date' => ['exclude_unless:timing,datum', 'required_if:timing,datum', 'date_format:Y-m-d', 'after_or_equal:today'],
        ] + $this->spamRules();
    }
}
