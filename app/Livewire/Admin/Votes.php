<?php

namespace App\Livewire\Admin;

use App\Actions\Governance\ManageVotes;
use App\Models\Vote;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Admins prepare member votes and see participation on /admin/abstimmungen (route admin.votes.index). Votes are
 * secret; tallies are visible here, too, only after the end.
 */
class Votes extends Component
{
    /** Number of answer fields in the form. */
    private const OPTION_FIELDS = 6;

    public string $title = '';

    public string $description = '';

    /** datetime-local value */
    public string $starts_at = '';

    /** datetime-local value */
    public string $ends_at = '';

    /** @var list<string> answers; empty ones are ignored */
    public array $options = [];

    /** Confirmation after creating a vote, announced through role="status". */
    public ?string $status = null;

    /** Admins only; starts with empty answer fields. */
    public function mount(): void
    {
        Gate::authorize('administrate');
        $this->options = array_fill(0, self::OPTION_FIELDS, '');
    }

    /** Create a vote with at least two distinct answers (trimmed, duplicates removed). */
    public function create(ManageVotes $votes): void
    {
        Gate::authorize('administrate');
        $input = $this->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:5000'],
            'starts_at' => ['required', 'date', 'after_or_equal:today'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'options' => ['required', 'array', 'max:8'],
            'options.*' => ['nullable', 'string', 'max:120'],
        ]);
        $input['options'] = array_values(array_unique(array_filter(array_map('trim', $input['options']), fn (string $option): bool => $option !== '')));
        if (count($input['options']) < 2) {
            $this->addError('options', 'Eine Abstimmung braucht mindestens zwei verschiedene Antworten.');

            return;
        }

        $votes->create(auth()->user(), $input);
        $this->reset('title', 'description', 'starts_at', 'ends_at');
        $this->options = array_fill(0, self::OPTION_FIELDS, '');
        $this->status = 'Die Abstimmung ist angelegt.';
    }

    /** Re-checked on every Livewire request, so a revoked admin role closes the page at once. */
    public function render(): View
    {
        Gate::authorize('administrate');

        return view('livewire.admin.votes', [
            'votes' => Vote::with('options')->withCount('participations')->orderByDesc('starts_at')->get(),
        ])->layout('layouts.staff', ['title' => 'Abstimmungen', 'active' => 'votes']);
    }
}
