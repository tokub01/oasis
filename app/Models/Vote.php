<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A member vote ("Jedes Mitglied hat eine Stimme, egal welche Stufe"). Runs from `starts_at` to `ends_at`; results
 * are shown only after it ended, so running tallies cannot sway anyone. Ballots are secret (see the migration).
 *
 * @property int $id
 * @property string $title
 * @property string $description
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 */
class Vote extends Model
{
    protected $fillable = ['title', 'description', 'starts_at', 'ends_at', 'created_by'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    /** @return HasMany<VoteOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(VoteOption::class)->orderBy('position');
    }

    /** @return HasMany<VoteParticipation, $this> */
    public function participations(): HasMany
    {
        return $this->hasMany(VoteParticipation::class);
    }

    public function isOpen(): bool
    {
        return $this->starts_at->isPast() && $this->ends_at->isFuture();
    }

    public function hasEnded(): bool
    {
        return $this->ends_at->isPast();
    }

    /** @param Builder<Vote> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->where('starts_at', '<=', now())->where('ends_at', '>', now());
    }

    /** @param Builder<Vote> $query */
    public function scopeEnded(Builder $query): void
    {
        $query->where('ends_at', '<=', now());
    }

    /**
     * The winning option(s) after the end; several on a tie, none without votes.
     *
     * @return list<string>
     */
    public function winners(): array
    {
        $max = $this->options->max('votes_count');

        return $max > 0 ? array_values($this->options->where('votes_count', $max)->map(fn (VoteOption $option): string => $option->label)->all()) : [];
    }
}
