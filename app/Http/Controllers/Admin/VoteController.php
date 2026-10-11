<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Governance\ManageVotes;
use App\Http\Controllers\Controller;
use App\Models\Vote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Admins prepare member votes and see participation; tallies are visible to them only after the end, too. */
class VoteController extends Controller
{
    public function index(): View
    {
        return view('admin.votes', [
            'votes' => Vote::with('options')->withCount('participations')->orderByDesc('starts_at')->get(),
        ]);
    }

    public function store(Request $request, ManageVotes $votes): RedirectResponse
    {
        $input = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:5000'],
            'starts_at' => ['required', 'date', 'after_or_equal:today'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'options' => ['required', 'array', 'max:8'],
            'options.*' => ['nullable', 'string', 'max:120'],
        ]);
        $input['options'] = array_values(array_unique(array_filter(array_map('trim', $input['options']), 'strlen')));
        if (count($input['options']) < 2) {
            return back()->withInput()->withErrors(['options' => 'Eine Abstimmung braucht mindestens zwei verschiedene Antworten.']);
        }
        $votes->create($request->user(), $input);

        return redirect()->route('admin.votes.index')->with('status', 'Die Abstimmung ist angelegt.');
    }
}
