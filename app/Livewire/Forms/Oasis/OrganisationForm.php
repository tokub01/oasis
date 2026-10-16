<?php

namespace App\Livewire\Forms\Oasis;

/** An organisation's enquiry about starting together ("Gemeinsam starten"). */
class OrganisationForm extends PublicForm
{
    public string $organisation = '';

    /** Rough number of people, a whole number of at least two (kept as text until validated). */
    public string $people = '';

    public string $email = '';

    /** @return array<string, list<string>> */
    protected function rules(): array
    {
        return [
            'organisation' => ['required', 'string', 'max:255'],
            'people' => ['required', 'integer', 'min:2', 'max:1000000'],
            'email' => ['required', 'email', 'max:254'],
        ] + $this->spamRules();
    }
}
