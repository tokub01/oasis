<?php

namespace App\Http\Requests\Oasis;

/**
 * Validates oasis.gruendung.store before a waitlist entry is saved.
 * Double opt-in requires explicit consent; duplicate email addresses are handled without enumeration.
 */
class WaitlistFormRequest extends OasisFormRequest
{
    /**
     * Require a valid address and consent while allowing an optional group.
     *
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
