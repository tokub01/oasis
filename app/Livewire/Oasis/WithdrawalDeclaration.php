<?php

namespace App\Livewire\Oasis;

use App\Actions\Oasis\RecordWithdrawal;
use App\Livewire\Concerns\SubmitsPublicForm;
use App\Livewire\Forms\Oasis\WithdrawalForm;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/** The "Vertrag widerrufen" form (§§ 355 ff. BGB) on /vertrag-widerrufen, embedded in oasis.forms._contract. */
class WithdrawalDeclaration extends Component
{
    use SubmitsPublicForm;

    public WithdrawalForm $form;

    /** Store the withdrawal, send the receipts and show the retainable confirmation with Berlin time. */
    public function submit(RecordWithdrawal $record): void
    {
        $this->throttle('widerrufen');
        $submission = $record->handle($this->form->validated());

        $this->showConfirmation('widerrufen', [
            ...$submission->only(['name', 'email', 'contract']),
            'received_at' => $submission->created_at->timezone('Europe/Berlin')->format('d.m.Y H:i:s T'),
            'contracted_on' => $submission->contracted_on?->format('d.m.Y'),
        ]);
    }

    /** The withdrawal form. */
    public function render(): View
    {
        return view('livewire.oasis.withdrawal-declaration');
    }
}
