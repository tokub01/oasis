<?php

namespace App\Http\Requests\Oasis;

class ContentReportFormRequest extends OasisFormRequest
{
    /**
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
