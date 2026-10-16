<?php

namespace App\Livewire\Forms\Oasis;

/** A DSA Art. 16 notice about content someone considers illegal; open to everyone, also without an account. */
class ContentReportForm extends PublicForm
{
    public string $url = '';

    public string $reason = '';

    public string $name = '';

    public string $email = '';

    /** Good-faith declaration required by DSA Art. 16(2)(d). */
    public bool $truthful = false;

    /** @return array<string, list<string>> */
    protected function rules(): array
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
