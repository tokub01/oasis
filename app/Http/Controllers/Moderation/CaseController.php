<?php

namespace App\Http\Controllers\Moderation;

use App\Actions\Moderation\AssignCase;
use App\Actions\Moderation\DecideCase;
use App\Enums\Moderation\Ground;
use App\Enums\Moderation\Outcome;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Moderation\AssignCaseRequest;
use App\Http\Requests\Moderation\DecisionRequest;
use App\Models\ModerationCase;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/** Thin staff endpoints; workflow actions own authorization, locking and audit writes. */
class CaseController extends Controller
{
    /** Render Claude's Livewire moderation queue. */
    public function index(): View
    {
        Gate::authorize('viewModeration');

        return view('moderation.index');
    }

    /** Provide case history and staff choices without exporting reporter contacts. */
    public function show(ModerationCase $case): View
    {
        Gate::authorize('view', $case);

        return view('moderation.cases.show', [
            'case' => $case->load(['source', 'subjectUser', 'assignee', 'decisions.decider', 'complaints.reviewer', 'events.actor']),
            'staff' => User::whereIn('role', [Role::Moderator, Role::Admin])->orderBy('display_name')->get(['id', 'display_name', 'role']),
            'outcomes' => Outcome::cases(),
            'grounds' => Ground::cases(),
        ]);
    }

    /** Assign the case to an existing staff member and return to its history. */
    public function assign(AssignCaseRequest $request, ModerationCase $case, AssignCase $action): RedirectResponse
    {
        $action->handle($case, $request->user(), User::findOrFail((int) $request->validated('assigned_to')));

        return to_route('moderation.cases.show', $case)->with('status', 'Der Fall wurde zugewiesen.');
    }

    /** Save a human decision and deliver notices after committing the case history. */
    public function decide(DecisionRequest $request, ModerationCase $case, DecideCase $action): RedirectResponse
    {
        $action->handle($case, $request->user(), $request->validated());

        return to_route('moderation.cases.show', $case)->with('status', 'Die Entscheidung wurde gespeichert und mitgeteilt.');
    }
}
