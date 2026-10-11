<?php

namespace App\Http\Requests\Oasis;

/**
 * Validates oasis.widerrufen.store for a withdrawal declaration under §§ 355 ff. BGB.
 * The contract date helps identification but is optional.
 */
class WithdrawalFormRequest extends OasisFormRequest
{
    /**
     * Require identification and the membership contract, with an optional ISO contract date.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:254'],
            'contract' => ['required', 'in:Oasis-Mitgliedschaft'],
            'contracted_on' => ['nullable', 'date_format:Y-m-d'],
        ] + $this->spamRules();
    }
}
