<?php

namespace App\Http\Requests\Oasis;

class WithdrawalFormRequest extends OasisFormRequest
{
    /**
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
