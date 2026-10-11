<?php

namespace App\Http\Requests\Oasis;

/**
 * Validates public content notices at oasis.melden.store under DSA Art. 16.
 * Accepts HTTP(S) references only and requires the good-faith declaration.
 */
class ContentReportFormRequest extends OasisFormRequest
{
    /**
     * Require a content reference, explanation, contact details and the good-faith declaration.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'url:http,https', 'max:2048'],
            'reason' => ['required', 'string', 'max:10000'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:254'],
            'truthful' => ['accepted'],
        ] + $this->spamRules();
    }
}
