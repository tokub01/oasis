<?php

namespace App\Livewire\Moderation;

use App\Actions\Moderation\ReviewComplaint;
use App\Enums\Moderation\Ground;
use App\Enums\Moderation\Outcome;
use App\Livewire\Moderation\Concerns\ModerationValidation;
use App\Livewire\Moderation\Forms\DecisionForm;
use App\Models\ModerationComplaint;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Review of an internal complaint against a moderation decision on /moderation/complaints/{complaint}
 * (route moderation.complaints.show). DSA Art. 20 and AGB § 12: a different team member than the original decider
 * reviews it – the policy `reviewComplaint` enforces that, here and again inside ReviewComplaint.
 *
 * "Upheld" needs a reason for the result. "Overturned" needs a complete new statement of reasons (DecisionForm);
 * its explanation is also the reason given for the result, so the affected person reads one consistent text.
 */
class ComplaintReview extends Component
{
    use ModerationValidation;

    #[Locked]
    public ModerationComplaint $complaint;

    /** "upheld" or "overturned"; switching shows the matching fields at once. */
    public string $result = '';

    /** Reason for an upheld decision. */
    public string $explanation = '';

    /** The replacing decision when the complaint is upheld against the original decision ("overturned"). */
    public DecisionForm $decision;

    /** Confirmation of the last action, announced through role="status". */
    public ?string $status = null;

    /** Staff may read every complaint; whether they may decide it is shown in the view. */
    public function mount(ModerationComplaint $complaint): void
    {
        Gate::authorize('view', $complaint);
        $this->complaint = $complaint;
    }

    /** Save the result, and for "overturned" the replacing decision; both parties are notified after the commit. */
    public function review(ReviewComplaint $action): void
    {
        Gate::authorize('reviewComplaint', $this->complaint);
        $input = $this->validate(['result' => ['required', Rule::in(['upheld', 'overturned'])]]
            + ($this->result === 'upheld' ? ['explanation' => ['required', 'string', 'max:10000']] : []));
        $data = $input['result'] === 'overturned' ? ['result' => 'overturned', ...$this->decision->statement()] : $input;

        $action->handle($this->complaint, auth()->user(), $data);
        $this->reset('result', 'explanation');
        $this->decision->reset();
        $this->status = 'Die Beschwerde wurde geprüft und das Ergebnis mitgeteilt.';
    }

    /** Re-checked on every Livewire request, so a revoked staff role closes the page at once. */
    public function render(): View
    {
        Gate::authorize('view', $this->complaint);
        $this->complaint->refresh()->load(['case', 'decision.decider', 'submitter', 'reviewer']);

        return view('livewire.moderation.complaint-review', [
            'outcomes' => Outcome::cases(),
            'grounds' => Ground::cases(),
        ])->layout('layouts.staff', ['title' => 'Widerspruch zu Fall #'.$this->complaint->case->id, 'active' => 'queue']);
    }
}
