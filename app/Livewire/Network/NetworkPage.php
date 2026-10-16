<?php

namespace App\Livewire\Network;

use App\Livewire\Concerns\InteractsWithPosts;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/** Shared network shell, current-account authorization and post-card endpoints for full-page lists. */
abstract class NetworkPage extends Component
{
    use InteractsWithPosts, WithPagination;

    /** Resolve the current verified account again on every request, never from the browser snapshot. */
    protected function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->hasVerifiedEmail(), 403);

        return $user;
    }

    /** Render domain query data without serializing its models as public component state.
     * @param  view-string  $view
     * @param  array<string, mixed>  $data
     */
    protected function page(string $view, array $data, string $title, string $active = 'quelle', bool $public = false): View
    {
        if (! $public) {
            $this->actor();
        }

        return view($view, $data)->layout($public && ! auth()->check() ? 'layouts.oasis' : 'layouts.app', [
            'title' => $title, 'active' => $active, 'wide' => true,
        ]);
    }
}
