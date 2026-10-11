<?php

namespace App\Http\Requests\Oasis;

class WaitlistFormRequest extends OasisFormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:254'],
            'group' => ['nullable', 'string', 'max:255'],
            'consent' => ['accepted'],
        ] + $this->spamRules();
    }
}
