<?php

namespace App\Http\Requests\Oasis;

/**
 * Validates organisation enquiries at oasis.gemeinsam.store.
 * The people count is an estimate for planning a group, not a purchase quantity.
 */
class OrganisationFormRequest extends OasisFormRequest
{
    /**
     * Require the organisation, at least two whole people and a valid reply address.
     *
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
