<?php

namespace App\Livewire\Moderation;

use App\Actions\Moderation\AssignCase;
use App\Actions\Moderation\DecideCase;
use App\Enums\Moderation\Ground;
use App\Enums\Moderation\Outcome;
use App\Enums\Role;
use App\Livewire\Moderation\Concerns\ModerationValidation;
use App\Livewire\Moderation\Forms\DecisionForm;
use App\Models\ModerationCase;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * One moderation case on /moderation/cases/{case} (route moderation.cases.show): the report, the case history, the
 * assignment and the first human decision with its statement of reasons (DSA Art. 17).
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

    /** Re-checked on every Livewire request, so a revoked staff role closes the page at once. */
    public function render(): View
    {
        Gate::authorize('view', $this->case);
        $this->case->refresh()->load(['source', 'subjectUser', 'assignee', 'decisions.decider', 'complaints.reviewer', 'events.actor']);

        return view('livewire.moderation.case-detail', [
            'staff' => User::whereIn('role', [Role::Moderator, Role::Admin])->orderBy('display_name')->get(['id', 'display_name', 'role']),
            'outcomes' => Outcome::cases(),
            'grounds' => Ground::cases(),
        ])->layout('layouts.staff', ['title' => 'Fall #'.$this->case->id, 'active' => 'queue']);
    }
}
