<?php

namespace App\Livewire\Oasis;

use App\Actions\Oasis\RecordOrganisationEnquiry;
use App\Livewire\Concerns\SubmitsPublicForm;
use App\Livewire\Forms\Oasis\OrganisationForm;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/** The enquiry form for organisations on /gemeinsam-starten. */
class OrganisationEnquiry extends Component
{
    use SubmitsPublicForm;

    public OrganisationForm $form;

    /** Store the enquiry and notify the operator. */
    public function submit(RecordOrganisationEnquiry $record): void
    {
        $this->throttle('gemeinsam');
        $record->handle($this->form->validated());
        $this->showConfirmation('gemeinsam');
    }

    /** The enquiry form. */
    public function render(): View
    {
        return view('livewire.oasis.organisation-enquiry');
    }
}
