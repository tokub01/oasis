<?php

namespace App\Livewire\Search;

use App\Livewire\Network\NetworkPage;
use App\Queries\Network\PostFeed;
use App\Queries\Search\DirectorySearch;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Url;

/** Live, URL-bound directory search; authorization and its original thirty-per-minute budget apply on updates. */
class Index extends NetworkPage
{
    #[Url(as: 'q')]
    public string $q = '';

    private bool $budgetChecked = false;

    private bool $budgetAvailable = true;

    /** Route searches and reactive searches use the same validation and hashtag redirect. */
    public function mount(): void
    {
        $this->budgetChecked = true;
        $this->actor();
        if (preg_match('/^#('.DirectorySearch::TAG_PATTERN.')$/u', trim($this->q), $matches)) {
            $this->redirectRoute('search.tags.show', mb_strtolower($matches[1]), navigate: true);
        }
    }

    /** Reset cursor state before resolving newly typed search input. */
    public function updatedQ(): void
    {
        $this->search();
    }

    /** Re-authorize all searches, bound input and prevent Livewire requests from bypassing the route budget. */
    public function search(): void
    {
        $this->actor();
        $this->validate(['q' => ['nullable', 'string', 'max:100']]);
        if (! $this->consumeBudget()) {
            return;
        }
        $this->resetPage('cursor');
        if (preg_match('/^#('.DirectorySearch::TAG_PATTERN.')$/u', trim($this->q), $matches)) {
            $this->redirectRoute('search.tags.show', mb_strtolower($matches[1]), navigate: true);
        }
    }

    /** Count every update that will run directory queries, including pagination and manual refreshes. */
    private function consumeBudget(): bool
    {
        if (! $this->budgetChecked) {
            $this->budgetChecked = true;
            $this->resetValidation('q');
            $this->budgetAvailable = ! RateLimiter::tooManyAttempts('search:'.auth()->id(), 30);
            if ($this->budgetAvailable) {
                RateLimiter::hit('search:'.auth()->id(), 60);
            } else {
                $this->addError('q', 'Du hast gerade viel gesucht. Warte bitte eine Minute.');
            }
        }

        return $this->budgetAvailable;
    }

    /** Render only authorized results; reject oversized URL or browser property values before database search. */
    public function render(): View
    {
        $viewer = $this->actor();
        $budgetAvailable = $this->consumeBudget();
        $valid = mb_strlen($this->q) <= 100;
        if (! $valid) {
            $this->addError('q', 'Dein Suchbegriff darf höchstens 100 Zeichen lang sein.');
        }
        $term = $valid ? trim($this->q) : '';
        $searching = $budgetAvailable && $valid && mb_strlen($term) >= DirectorySearch::MIN_LENGTH;
        $directory = app(DirectorySearch::class);

        return $this->page('livewire.search.index', ['term' => $term, 'searching' => $searching,
            'people' => $searching ? $directory->people($term, $viewer) : collect(),
            'oases' => $searching ? $directory->oases($term) : collect(),
            'posts' => $searching ? app(PostFeed::class)->matching($term, $viewer) : null], 'Suche', 'entdecken');
    }
}
