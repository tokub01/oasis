<?php

namespace App\Livewire\Moderation;

use App\Actions\Moderation\AssignCase;
use App\Actions\Moderation\DecideCase;
use App\Actions\Moderation\EscalateCase;
use App\Actions\Moderation\ManageEscalation;
use App\Enums\Moderation\Escalation;
use App\Enums\Moderation\Ground;
use App\Enums\Moderation\Outcome;
use App\Enums\Role;
use App\Livewire\Moderation\Concerns\ModerationValidation;
use App\Livewire\Moderation\Forms\DecisionForm;
use App\Models\ModerationCase;
use App\Models\Post;
use App\Models\User;
use App\Support\Moderation\CaseSubject;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * One moderation case on /moderation/cases/{case} (route moderation.cases.show): the report, the case history, the
 * assignment and the first human decision with its statement of reasons (DSA Art. 17), plus the escalation for threats
 * to life (Art. 18 DSA) and child abuse: checklist, secured evidence and a report staff record by hand. Staff escalate
 * and record reports; evidence and quarantine are for admins.
 *
 * The reporter's name and email are never loaded into the page. AssignCase and DecideCase lock the case and check
 * the policy again inside their transaction; the component checks before validating, so outsiders learn nothing.
 */
class CaseDetail extends Component
{
    use ModerationValidation;

    #[Locked]
    public ModerationCase $case;

    /** Id of the staff member chosen in the assignment select. */
    public ?int $assigned_to = null;

    public DecisionForm $decision;

    /** Confirmation of the last action, announced through role="status". */
    public ?string $status = null;

    /** Authority and reference of a report staff made by hand. */
    public string $reference = '';

    /** Staff only; the select starts with the current assignee, or with the signed-in staff member. */
    public function mount(ModerationCase $case): void
    {
        Gate::authorize('view', $case);
        $this->case = $case;
        $this->assigned_to = $case->assigned_to ?? auth()->user()->id;
    }

    /** Hand the case to a staff member; assigning an open case moves it into review. */
    public function assign(AssignCase $action): void
    {
        Gate::authorize('assign', $this->case);
        $this->validate(['assigned_to' => ['required', 'integer', Rule::exists('users', 'id')->whereIn('role', ['moderator', 'admin'])]]);
        $action->handle($this->case, auth()->user(), User::findOrFail($this->assigned_to));
        $this->status = 'Der Fall wurde zugewiesen.';
    }

    /** Save the human decision; DecideCase notifies the reporter and the affected person after the commit. */
    public function decide(DecideCase $action): void
    {
        Gate::authorize('decide', $this->case);
        $action->handle($this->case, auth()->user(), $this->decision->statement());
        $this->decision->reset();
        $this->status = 'Die Entscheidung wurde gespeichert und mitgeteilt.';
    }

    /** Escalate as a threat to life (Art. 18 DSA) or child abuse; the value comes from the button, anything else 422s. */
    public function escalate(string $type, EscalateCase $action): void
    {
        Gate::authorize('view', $this->case);
        $escalation = Escalation::tryFrom($type);
        abort_if($escalation === null, 422);
        $action->handle($this->case, auth()->user(), $escalation);
        $this->status = 'Der Fall ist eskaliert, die Beweise sind gesichert. Bitte jetzt die Checkliste abarbeiten.';
    }

    public function recordReport(ManageEscalation $action): void
    {
        Gate::authorize('view', $this->case);
        $this->resetErrorBag('reference');
        $action->recordReport($this->case, auth()->user(), $this->reference);
        $this->reset('reference');
        $this->status = 'Die Behördenmeldung ist vermerkt.';
    }

    public function extendEvidence(ManageEscalation $action): void
    {
        Gate::authorize('administrate');
        $action->extendEvidence($this->case, auth()->user());
        $this->status = 'Die Beweise werden länger aufbewahrt.';
    }

    public function deleteEvidence(ManageEscalation $action): void
    {
        Gate::authorize('administrate');
        $action->deleteEvidence($this->case, auth()->user());
        $this->status = 'Die Beweise sind gelöscht.';
    }

    public function liftQuarantine(ManageEscalation $action): void
    {
        Gate::authorize('administrate');
        $action->liftQuarantine($this->case, auth()->user());
        $this->status = 'Die Quarantäne ist aufgehoben. Der Tropfen bleibt ausgeblendet, bis eine Entscheidung ihn freigibt.';
    }

    /** Re-checked on every Livewire request, so a revoked staff role closes the page at once. */
    public function render(): View
    {
        Gate::authorize('view', $this->case);
        $this->case->refresh()->load(['source', 'subjectUser', 'assignee', 'decisions.decider', 'complaints.reviewer', 'events.actor',
            'evidence', 'escalatedBy']);
        $subject = $this->case->escalation !== null ? CaseSubject::resolve($this->case) : null;

        return view('livewire.moderation.case-detail', [
            'escalations' => Escalation::cases(),
            'quarantined' => $subject instanceof Post && $subject->quarantined_at !== null,
            'staff' => User::whereIn('role', [Role::Moderator, Role::Admin])->orderBy('display_name')->get(['id', 'display_name', 'role']),
            'outcomes' => Outcome::cases(),
            'grounds' => Ground::cases(),
        ])->layout('layouts.staff', ['title' => 'Fall #'.$this->case->id, 'active' => 'queue']);
    }
}
