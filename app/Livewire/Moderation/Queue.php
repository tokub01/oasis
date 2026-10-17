<?php

namespace App\Livewire\Moderation;

use App\Enums\Moderation\CaseStatus;
use App\Enums\Moderation\Priority;
use App\Models\ModerationCase;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Moderation queue on /moderation (view moderation.index embeds it).
 *
 * Lists moderation cases for staff with filters that are kept in the URL, so a filtered queue can be bookmarked
 * and the page still works on a full reload. Open and urgent work comes first. Reporter contact data is never
 * loaded here; the queue shows only the reported URL, the reason excerpt and the case state.
 */
class Queue extends Component
{
    use WithPagination;

    /** Case status filter: one of CaseStatus values, or "active" (open, in review, complaint open), or "all". */
    #[Url(as: 'status')]
    public string $status = 'active';

    /** Priority filter: a Priority value or "all". */
    #[Url(as: 'prioritaet')]
    public string $priority = 'all';

    /** Only cases assigned to the signed-in staff member. */
    #[Url(as: 'meine')]
    public bool $mine = false;

    /** Free-text search in the reported URL. */
    #[Url(as: 'suche')]
    public string $search = '';

    /** Only staff may open the queue (gate viewModeration, also enforced by the route middleware). */
    public function mount(): void
    {
        Gate::authorize('viewModeration');
    }

    /** Any filter change starts again on page 1. */
    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'priority', 'mine', 'search'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Livewire re-renders on its own update requests, which do not run mount() again. Checking here keeps the
     * queue closed as soon as someone loses the staff role mid-session (found in Codex' review).
     */
    public function render(): View
    {
        Gate::authorize('viewModeration');

        return view('livewire.moderation.queue', [
            'cases' => $this->query()->paginate(25),
            'statuses' => CaseStatus::cases(),
            'priorities' => Priority::cases(),
        ]);
    }

    /**
     * The filtered case query: urgent first, then oldest first (first in, first out).
     *
     * @return Builder<ModerationCase>
     */
    private function query(): Builder
    {
        $active = [CaseStatus::Open, CaseStatus::InReview, CaseStatus::ComplaintOpen];

        return ModerationCase::query()
            ->with(['assignee:id,display_name', 'source', 'latestDecision'])
            ->when($this->status === 'active', fn (Builder $q) => $q->whereIn('status', $active))
            ->when(CaseStatus::tryFrom($this->status), fn (Builder $q, CaseStatus $s) => $q->where('status', $s))
            ->when(Priority::tryFrom($this->priority), fn (Builder $q, Priority $p) => $q->where('priority', $p))
            ->when($this->mine, fn (Builder $q) => $q->where('assigned_to', auth()->id()))
            ->when(trim($this->search) !== '', fn (Builder $q) => $q->where('subject_url', 'like', '%'.trim($this->search).'%'))
            ->orderByRaw('escalation is not null desc')
            ->orderByRaw('priority = ? desc', [Priority::Urgent->value])
            ->oldest();
    }
}
