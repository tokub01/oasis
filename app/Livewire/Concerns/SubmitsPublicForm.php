<?php

namespace App\Livewire\Concerns;

use Illuminate\Validation\ValidationException;

/**
 * Shared steps of the public Oasis forms: a submission budget per form and IP, and the hand-over to the
 * confirmation page (routes oasis.bestaetigung.<form>, which are 404 unless a submission just happened).
 */
trait SubmitsPublicForm
{
    use LimitsAttempts;

    /**
     * Allow six submissions per minute and IP. Every form has its own budget, so a few waitlist attempts can never
     * block a cancellation, which § 312k BGB must always allow.
     *
     * @throws ValidationException when the budget is used up (shown as a form error, nothing is stored)
     */
    protected function throttle(string $form): void
    {
        $this->limitAttempts('oasis-'.$form.'|'.request()->ip(), 6);
    }

    /**
     * Unlock the confirmation page for this submission and go there without a full reload.
     *
     * @param  array<string, mixed>|true  $details  shown on the confirmation page (retainable copy), or just a marker
     */
    protected function showConfirmation(string $form, array|bool $details = true): void
    {
        session()->flash('oasis.submitted.'.$form, $details);
        $this->redirectRoute('oasis.bestaetigung.'.$form, navigate: true);
    }
}
