<?php

namespace App\Livewire\Concerns;

use App\Support\Security\BotProtection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

/**
 * Shared steps of the public Oasis forms: bot traps (the honeypot form.website of PublicForm and a time trap,
 * BotProtection), a submission budget per form and IP, and the hand-over to the confirmation page (routes
 * oasis.bestaetigung.<form>, which are 404 unless a submission just happened).
 */
trait SubmitsPublicForm
{
    use LimitsAttempts;

    /** Encrypted time the form was shown; set by the server only. */
    #[Locked]
    public string $formToken = '';

    /** Livewire trait hook: every public form starts its time trap. */
    public function mountSubmitsPublicForm(): void
    {
        $this->formToken = BotProtection::token();
    }

    /**
     * Refuse submissions that look automated, then allow six per minute and IP. Every form has its own budget, so a few waitlist attempts can never
     * block a cancellation, which § 312k BGB must always allow.
     *
     * @throws ValidationException when the budget is used up (shown as a form error, nothing is stored)
     */
    protected function throttle(string $form): void
    {
        // A filled honeypot is refused by PublicForm::spamRules() at form.website; here it is only counted.
        if ($this->form->website !== '') {
            BotProtection::count('honeypot');
        }
        if (BotProtection::check(null, $this->formToken) !== null) {
            $this->formToken = BotProtection::token();
            throw ValidationException::withMessages(['throttle' => 'Das hat nicht geklappt. Bitte prüfe deine Angaben und schick das Formular noch einmal ab.']);
        }
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
