<?php

namespace App\Actions\Governance;

use App\Models\User;
use App\Models\Vote;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creating votes (admins) and casting secret ballots (members with an active membership).
 *
 * Secrecy: casting inserts a participation row (unique per vote and member, so nobody votes twice) and increments
 * the chosen option's counter in the same transaction. Nothing stores which member chose which option.
 */
class ManageVotes
{
    /**
     * @param  array{title: string, description: string, starts_at: string, ends_at: string, options: list<string>}  $input
     */
    public function create(User $admin, array $input): Vote
    {
        return DB::transaction(function () use ($admin, $input): Vote {
            $vote = Vote::create([
                'title' => $input['title'], 'description' => $input['description'],
                'starts_at' => $input['starts_at'], 'ends_at' => $input['ends_at'], 'created_by' => $admin->id,
            ]);
            foreach ($input['options'] as $position => $label) {
                $vote->options()->create(['label' => $label, 'position' => $position]);
            }

            return $vote;
        });
    }

    /** @throws ValidationException if the vote is not open, the option is foreign or the member already voted */
    public function cast(Vote $vote, User $member, int $optionId): void
    {
        if (! $vote->isOpen()) {
            throw ValidationException::withMessages(['option' => 'Diese Abstimmung läuft gerade nicht.']);
        }
        $option = $vote->options()->whereKey($optionId)->first();
        if ($option === null) {
            throw ValidationException::withMessages(['option' => 'Bitte wähle eine der Antworten.']);
        }

        try {
            DB::transaction(function () use ($vote, $member, $option): void {
                $vote->participations()->create(['user_id' => $member->id, 'voted_on' => now()->toDateString()]);
                $option->increment('votes_count');
            });
        } catch (QueryException) {
            // Primary key (vote, member) already exists: a second ballot, e.g. a double click.
            throw ValidationException::withMessages(['option' => 'Du hast bei dieser Abstimmung schon abgestimmt.']);
        }
    }
}
