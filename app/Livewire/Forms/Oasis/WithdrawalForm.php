<?php

namespace App\Livewire\Forms\Oasis;

/** The withdrawal declaration (§§ 355 ff. BGB); the contract date is optional. */
class WithdrawalForm extends PublicForm
{
    public string $name = '';

    public string $email = '';

    /** The only contract that can be withdrawn from; validated so a manipulated value is rejected. */
    public string $contract = 'Oasis-Mitgliedschaft';

    /** Y-m-d or empty. */
    public string $contracted_on = '';

    /** @return array<string, list<string>> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:254'],
            'contract' => ['required', 'in:Oasis-Mitgliedschaft'],
            'contracted_on' => ['nullable', 'date_format:Y-m-d'],
        ] + $this->spamRules();
    }
}
