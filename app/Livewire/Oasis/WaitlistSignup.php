<?php

namespace App\Livewire\Oasis;

use App\Actions\Oasis\JoinWaitlist;
use App\Livewire\Concerns\SubmitsPublicForm;
use App\Livewire\Forms\Oasis\WaitlistForm;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The founding start-list form on /gruendung. The response is the same for new, unconfirmed and confirmed
 * addresses, so the form never reveals who is already on the list.
 */
class WaitlistSignup extends Component
{
    use SubmitsPublicForm;

    public WaitlistForm $form;

    /** Record consent, ask for double opt-in and show the confirmation page. */
    public function submit(JoinWaitlist $join): void
    {
        $this->throttle('gruendung');
        $join->handle($this->form->validated());
        $this->showConfirmation('gruendung');
    }

    /** The start-list form. */
    public function render(): View
    {
        return view('livewire.oasis.waitlist-signup');
    }
}
