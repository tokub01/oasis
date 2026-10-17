<?php

namespace App\Livewire\Forms\Oasis;

use App\Rules\NotDisposableEmail;

/** Sign-up for the founding start list; consent is required before any e-mail is sent. */
class WaitlistForm extends PublicForm
{
    public string $email = '';

    /** Optional: who the person wants to start with. */
    public string $group = '';

    public bool $consent = false;

    /** @return array<string, list<mixed>> */
    protected function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:254', new NotDisposableEmail],
            'group' => ['nullable', 'string', 'max:255'],
            'consent' => ['accepted'],
        ] + $this->spamRules();
    }
}
