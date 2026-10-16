<?php

namespace App\Livewire\Notifications;

use App\Support\Notifications\HintPresentation;
use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The private hint inbox on /app/hinweise (route notifications.index), usable by read-only members too.
 *
 * Newest first, 20 per page, grouped by calendar day and week in Europe/Berlin. HintPresentation hides hints whose
 * content is gone or whose actor is blocked or muted. The read actions only ever touch the signed-in account's own
 * hints: an unknown or foreign id fails with 404, so nothing reveals whether it exists.
 */
class Inbox extends Component
{
    use WithPagination;

    /** Confirmation of the last read action, announced through role="status". */
    public ?string $status = null;

    /** Mark one of the signed-in account's hints as read. */
    public function markRead(string $id): void
    {
        auth()->user()->notifications()->whereKey($id)->firstOrFail()->markAsRead();
        $this->status = 'Der Hinweis ist gelesen.';
    }

    /** Mark all own hints as read, including those on later pages, in one update. */
    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
        $this->status = 'Alle Hinweise sind gelesen.';
    }

    /** The current page of own hints, grouped, in the member layout. */
    public function render(HintPresentation $presentation): View
    {
        $user = auth()->user();
        $hints = $user->notifications()->orderByDesc('created_at')->orderByDesc('id')->paginate(20);

        return view('livewire.notifications.inbox', [
            'hints' => $hints,
            'groups' => $this->group($hints, $presentation->forMany($hints, $user)),
        ])->layout('layouts.app', ['title' => 'Hinweise', 'active' => 'hinweise', 'wide' => true]);
    }

    /**
     * Visible hints of the current page under the headings "Heute", "Diese Woche" and "Früher".
     *
     * @param  LengthAwarePaginator<int, DatabaseNotification>  $hints
     * @param  array<string, array{label: string, url: string, excerpt: string}>  $items  visible items keyed by hint id
     * @return array<string, list<array{hint: DatabaseNotification, item: array{label: string, url: string, excerpt: string}}>>
     */
    private function group(LengthAwarePaginator $hints, array $items): array
    {
        $startOfWeek = now('Europe/Berlin')->startOfWeek();
        $groups = [];
        foreach ($hints as $hint) {
            if (! isset($items[$hint->id])) {
                continue;
            }
            $date = $hint->created_at->copy()->timezone('Europe/Berlin');
            $group = $date->isToday() ? 'Heute' : ($date->greaterThanOrEqualTo($startOfWeek) ? 'Diese Woche' : 'Früher');
            $groups[$group][] = ['hint' => $hint, 'item' => $items[$hint->id]];
        }

        return $groups;
    }
}
