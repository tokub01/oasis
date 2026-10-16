<?php

namespace App\Livewire\Oasis;

use App\Actions\Oasis\RecordCancellation;
use App\Livewire\Concerns\SubmitsPublicForm;
use App\Livewire\Forms\Oasis\CancellationForm;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The "Verträge hier kündigen" form (§ 312k BGB) on /vertraege-kuendigen, embedded in oasis.forms._contract.
 * Works without an account and without retention offers; the reason and date fields appear only when they apply.
 */
class CancellationDeclaration extends Component
{
    use SubmitsPublicForm;

    public CancellationForm $form;

    /** Store the declaration, send the receipts and show the retainable confirmation with Berlin time. */
    public function submit(RecordCancellation $record): void
    {
        $this->throttle('kuendigen');
        $submission = $record->handle($this->form->validated());

        $this->showConfirmation('kuendigen', [
            ...$submission->only(['name', 'email', 'username', 'contract', 'type', 'reason', 'timing']),
            'received_at' => $submission->created_at->timezone('Europe/Berlin')->format('d.m.Y H:i:s T'),
            'effective_date' => $submission->effective_date?->format('d.m.Y'),
        ]);
    }

    /** The cancellation form; inapplicable fields (reason, date) are not rendered. */
    public function render(): View
    {
        return view('livewire.oasis.cancellation-declaration');
    }
}
