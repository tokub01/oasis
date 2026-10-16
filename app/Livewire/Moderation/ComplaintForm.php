<?php

namespace App\Livewire\Moderation;

use App\Actions\Moderation\SubmitComplaint;
use App\Livewire\Moderation\Concerns\ModerationValidation;
use App\Models\ModerationDecision;
use App\Support\Moderation\ComplaintAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Complaint form against a moderation decision (DSA Art. 20, AGB § 12), reachable two ways:
 *  - route moderation.complaint.create: the signed link from the decision mail, for guests too; the `signed`
 *    middleware checks the link, and its `recipient` query ("reporter" or "subject") is locked in at mount,
 *  - route moderation.complaint.account: the signed-in affected member (`via` = "account").
 *
 * The sender's email is resolved server-side by ComplaintAccess on every action and never enters the page. A
 * reporter sees only the outcome, not the facts and reasons meant for the affected person.
 */
class ComplaintForm extends Component
{
    use ModerationValidation;

    /** Complaints per minute and IP address, independent of validation (as the former throttle middleware). */
    private const ATTEMPTS_PER_MINUTE = 5;

    #[Locked]
    public ModerationDecision $decision;

    /** ComplaintAccess selector: "reporter", "subject" (signed link) or "account". */
    #[Locked]
    public string $recipient = '';

    public string $reason = '';

    /** True once the complaint is stored; the page then shows the confirmation. Only the server sets it. */
    #[Locked]
    public bool $submitted = false;

    /**
     * Only reachable through the two routes above, so the selector comes from the checked link or the account.
     *
     * @param  string  $via  route default: "link" or "account"
     */
    public function mount(ModerationDecision $decision, ComplaintAccess $access, string $via = 'link'): void
    {
        $this->decision = $decision;
        $this->recipient = $via === 'account' ? 'account' : (string) request()->query('recipient');
        Gate::authorize('submitComplaint', [$decision, $access->recipient($decision, $this->recipient, auth()->user())]);
    }

    /** Store the complaint for the resolved recipient; the deadline and duplicates are checked again on submit. */
    public function submit(ComplaintAccess $access, SubmitComplaint $action): void
    {
        $email = $access->recipient($this->decision, $this->recipient, auth()->user());
        Gate::authorize('submitComplaint', [$this->decision, $email]);
        if (! $this->withinRateLimit()) {
            $this->resetErrorBag();
            $this->addError('reason', 'Zu viele Beschwerden. Bitte versuche es in einer Minute erneut.');

            return;
        }
        $this->validate(['reason' => ['required', 'string', 'max:10000']]);

        $action->handle($this->decision, $email, $this->reason, auth()->user());
        $this->submitted = true;
    }

    /**
     * The decision as shown to this recipient and the six-month deadline; members see it in the app layout, guests
     * on the public one.
     */
    public function render(): View
    {
        $layout = $this->recipient === 'account' ? 'layouts.app' : 'layouts.oasis';
        $title = $this->submitted ? 'Widerspruch eingegangen' : 'Widerspruch einlegen';

        return view('livewire.moderation.complaint-form', [
            'shown' => $this->shownDecision(),
            'deadline' => $this->decision->notified_at->copy()->addMonthsNoOverflow(6),
        ])->layout($layout, ['title' => $title, 'description' => 'Widerspruch gegen eine Moderationsentscheidung.', 'noindex' => true]);
    }

    /** Count this attempt; false when the IP address has used up its attempts for the current minute. */
    private function withinRateLimit(): bool
    {
        $key = 'moderation-complaints:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, self::ATTEMPTS_PER_MINUTE)) {
            return false;
        }
        RateLimiter::hit($key, 60);

        return true;
    }

    /** The decision as this recipient may read it: a reporter gets the outcome only. */
    private function shownDecision(): ModerationDecision
    {
        if ($this->recipient !== 'reporter') {
            return $this->decision;
        }
        $shown = clone $this->decision;
        $shown->facts = '';
        $shown->explanation = 'Deine Meldung wurde geprüft. Ergebnis: '.$this->decision->outcome->label().'.';
        $shown->rule_reference = '';

        return $shown;
    }
}
