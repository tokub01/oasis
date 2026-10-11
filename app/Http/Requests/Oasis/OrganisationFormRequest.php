<?php

namespace App\Http\Requests\Oasis;

class OrganisationFormRequest extends OasisFormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'organisation' => ['required', 'string', 'max:255'],
            'people' => ['required', 'integer', 'min:2', 'max:1000000'],
            'email' => ['required', 'email', 'max:254'],
        ] + $this->spamRules();
    }
}
