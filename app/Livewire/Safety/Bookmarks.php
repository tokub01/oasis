<?php

namespace App\Livewire\Safety;

use App\Livewire\Concerns\InteractsWithPosts;
use App\Models\User;
use App\Queries\Safety\BookmarkList;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Private bookmark list on /app/gemerkt (route safety.bookmarks): shows Tropfen saved by the signed-in member,
 * newest bookmarks first, with full post-card interactions via InteractsWithPosts.
 *
 * Bookmarks are strictly scoped to the authenticated account; unreadable, deleted, blocked or muted posts are
 * excluded by BookmarkList.
 */
class Bookmarks extends Component
{
    use InteractsWithPosts;
    use WithPagination;

    /**
     * Render the authenticated member's cursor-paginated bookmarks.
     */
    public function render(BookmarkList $query): View
    {
        $viewer = auth()->user();
        abort_unless($viewer instanceof User && $viewer->hasVerifiedEmail(), 403);

        return view('livewire.safety.bookmarks', [
            'posts' => $query->paginate($viewer),
        ])->layout('layouts.app', ['title' => 'Merkliste', 'active' => 'gemerkt', 'wide' => true]);
    }
}
