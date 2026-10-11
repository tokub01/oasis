<?php

namespace App\Http\Controllers\Governance;

use App\Actions\Governance\ManageVotes;
use App\Http\Controllers\Controller;
use App\Models\Vote;
use App\Models\VoteParticipation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** "Abstimmungen" for members: running votes to take part in, ended votes with results. */
class BallotController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('app.votes', [
            'open' => Vote::open()->with('options')->orderBy('ends_at')->get(),
            'ended' => Vote::ended()->with('options')->orderByDesc('ends_at')->limit(20)->get(),
            'votedIds' => VoteParticipation::where('user_id', $user->id)->pluck('vote_id')->all(),
            'canVote' => Gate::allows('vote'),
        ]);
    }

    public function store(Request $request, Vote $vote, ManageVotes $votes): RedirectResponse
    {
        Gate::authorize('vote');
        $input = $request->validate(['option' => ['required', 'integer']]);
        $votes->cast($vote, $request->user(), (int) $input['option']);

        return redirect()->route('app.votes')->with('status', 'Danke, deine Stimme ist gezählt. Wie du abgestimmt hast, speichern wir nicht.');
    }
}
