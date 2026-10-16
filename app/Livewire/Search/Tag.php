<?php

namespace App\Livewire\Search;

use App\Livewire\Network\NetworkPage;
use App\Queries\Network\PostFeed;
use App\Queries\Search\DirectorySearch;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;

/** Public chronological topic feed; each render applies visibility, blocking and muting through PostFeed. */
class Tag extends NetworkPage
{
    #[Locked]
    public string $tag;

    private bool $initialRequest = false;

    /** Only validated hashtag characters can enter the topic query. */
    public function mount(string $tag): void
    {
        $this->initialRequest = true;
        abort_unless(preg_match('/^'.DirectorySearch::TAG_PATTERN.'$/u', $tag) === 1, 404);
        $this->tag = mb_strtolower($tag);
    }

    /** Guests see only public posts; a changed authenticated audience is re-evaluated on every request. */
    public function render(): View
    {
        $this->resetValidation('tag');
        $budgetAvailable = true;
        if (! $this->initialRequest) {
            $key = 'search:'.(auth()->id() ?? request()->ip());
            $budgetAvailable = ! RateLimiter::tooManyAttempts($key, 30);
            if ($budgetAvailable) {
                RateLimiter::hit($key, 60);
            } else {
                $this->addError('tag', 'Du hast gerade viel gesucht. Warte bitte eine Minute.');
            }
        }

        return $this->page('livewire.search.tag', ['tag' => $this->tag,
            'posts' => $budgetAvailable ? app(PostFeed::class)->tagged($this->tag, auth()->user()) : new CursorPaginator([], 20)], '#'.$this->tag, 'entdecken', true);
    }
}
