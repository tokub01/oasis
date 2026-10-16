<?php

namespace App\Livewire\Governance;

use App\Actions\Governance\ManageVotes;
use App\Models\User;
use App\Models\Vote;
use App\Models\VoteParticipation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Member ballot page on /app/abstimmungen (route app.votes): shows running votes to participate in and completed votes
 * with their published results.
 *
 * Every member with an active membership has one secret vote per ballot (Gate 'vote'). Participation and option counts
 * are stored separately by ManageVotes::cast so no database record links a member to their chosen option.
 */
class Votes extends Component
{
    /**
     * Selected option ID per running vote ID for radio bindings (`options.{voteId}`).
     *
     * @var array<int, int|string|null>
     */
    public array $options = [];

    /** Confirmation message after casting a ballot, announced via role="status". */
    #[Locked]
    public string $status = '';

    /**
     * Cast a secret ballot on an open vote after verifying active membership entitlement.
     */
    public function cast(int $voteId, ManageVotes $votes): void
    {
        $user = $this->actor();
        Gate::authorize('vote');

        $vote = Vote::findOrFail($voteId);
        $chosen = $this->options[$voteId] ?? null;

        $validated = Validator::make(
            ['option' => $chosen],
            ['option' => ['required', 'integer']],
            ['option.required' => 'Bitte wähle eine Antwort aus.']
        )->validate();

        $votes->cast($vote, $user, (int) $validated['option']);

        $this->status = 'Danke, deine Stimme ist gezählt. Wie du abgestimmt hast, speichern wir nicht.';
    }

    /**
     * Render running and completed member votes.
     */
    public function render(): View
    {
        $user = $this->actor();

        return view('livewire.governance.votes', [
            'open' => Vote::open()->with('options')->orderBy('ends_at')->get(),
            'ended' => Vote::ended()->with('options')->orderByDesc('ends_at')->limit(20)->get(),
            'votedIds' => VoteParticipation::where('user_id', $user->id)->pluck('vote_id')->all(),
            'canVote' => Gate::allows('vote'),
        ])->layout('layouts.app', ['title' => 'Abstimmungen', 'active' => 'abstimmungen', 'wide' => true]);
    }

    /**
     * Re-authorize the signed-in, verified member on every request.
     */
    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->hasVerifiedEmail(), 403);

        return $user;
    }
}
