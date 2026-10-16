<?php

namespace App\Livewire\Oasis;

use App\Actions\Oasis\RecordContentReport;
use App\Livewire\Concerns\SubmitsPublicForm;
use App\Livewire\Forms\Oasis\ContentReportForm;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The DSA Art. 16 notice form on /inhalte-melden. A "Melden" link may pass the content URL as ?url=…; signed-in
 * members find their own name and address prefilled.
 */
class ContentNotice extends Component
{
    use SubmitsPublicForm;

    public ContentReportForm $form;

    /** Prefill the reported link from the query string and the member's own contact details. */
    public function mount(): void
    {
        $this->form->url = (string) request()->query('url', '');
        $this->form->name = (string) auth()->user()?->display_name;
        $this->form->email = (string) auth()->user()?->email;
    }

    /** Record the notice, acknowledge it and notify moderation. */
    public function submit(RecordContentReport $record): void
    {
        $this->throttle('melden');
        $record->handle($this->form->validated());
        $this->showConfirmation('melden');
    }

    /** The notice form with the prefilled link and contact details. */
    public function render(): View
    {
        return view('livewire.oasis.content-notice');
    }
}
