<?php

namespace App\Http\Controllers\Network;

use App\Actions\Network\ManageOasis;
use App\Enums\Network\OasisRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Network\OasisRequest;
use App\Models\Oasis;
use App\Models\User;
use App\Queries\Network\OasisStats;
use App\Queries\Network\PostFeed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Oases: list, create, read (also as guest for public ones), edit, host roles and host statistics. */
class OasisController extends Controller
{
    public function __construct(private readonly ManageOasis $oases) {}

    /** "Oasen": the member's own oases and all others, alphabetically. */
    public function index(Request $request): View
    {
        $mine = $request->user()->oases()->withCount('members')->orderBy('name')->get();

        return view('app.network.oases.index', [
            'mine' => $mine,
            'others' => Oasis::whereNotIn('id', $mine->pluck('id'))->withCount('members')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Oasis::class);

        return view('app.network.oases.form', ['oasis' => new Oasis(['visibility' => 'public', 'color' => 'spring'])]);
    }

    public function store(OasisRequest $request): RedirectResponse
    {
        Gate::authorize('create', Oasis::class);
        $oasis = $this->oases->create($request->user(), $request->validated());

        return redirect()->route('network.oases.show', $oasis)->with('status', 'Deine Oase ist eröffnet. Du bist Gastgeber:in.');
    }

    /** Unknown and unreadable oases are both 404. */
    public function show(Request $request, Oasis $oasis, PostFeed $feed): View
    {
        abort_unless(Gate::forUser($request->user())->allows('view', $oasis), 404);

        return view('app.network.oases.show', [
            'oasis' => $oasis->loadCount('members'),
            'hosts' => $oasis->hosts()->orderBy('display_name')->get(),
            'posts' => $feed->inOasis($oasis, $request->user()),
            'isMember' => $oasis->isMember($request->user()),
            'isHost' => $oasis->isHost($request->user()),
        ]);
    }

    public function edit(Oasis $oasis): View
    {
        Gate::authorize('host', $oasis);

        return view('app.network.oases.form', [
            'oasis' => $oasis,
            'members' => $oasis->members()->orderBy('display_name')->get(),
        ]);
    }

    public function update(OasisRequest $request, Oasis $oasis): RedirectResponse
    {
        Gate::authorize('host', $oasis);
        $this->oases->update($oasis, $request->validated());

        return redirect()->route('network.oases.show', $oasis)->with('status', 'Die Oase ist gespeichert.');
    }

    public function setRole(Request $request, Oasis $oasis, User $user): RedirectResponse
    {
        Gate::authorize('host', $oasis);
        $input = $request->validate(['role' => ['required', Rule::enum(OasisRole::class)]]);
        $this->oases->setRole($oasis, $user, OasisRole::from($input['role']));

        return back()->with('status', 'Die Rolle von '.$user->display_name.' ist geändert.');
    }

    /** Host statistics: sums and weekly activity, no lists of people. */
    public function stats(Oasis $oasis, OasisStats $stats): View
    {
        Gate::authorize('host', $oasis);

        return view('app.network.oases.stats', [
            'oasis' => $oasis, 'totals' => $stats->totals($oasis), 'weekly' => $stats->weekly($oasis),
        ]);
    }
}
