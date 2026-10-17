<?php

namespace App\Livewire\Network;

use App\Models\Post;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;

/** Authorized carry confirmation with the original Tropfen and a context-bound reactive composer. */
class PostCarry extends NetworkPage
{
    #[Locked]
    public int $postId;

    /** Bind the carry target; render and the nested composer recheck carry authorization. */
    public function mount(Post $post): void
    {
        $this->postId = $post->id;
    }

    /** Re-authorize the source after any state change; the nested composer repeats authorization on save. */
    public function render(): View
    {
        $this->actor();
        $post = Post::with(['author.profile', 'images'])->findOrFail($this->postId);
        Gate::authorize('carry', $post);

        return $this->page('livewire.network.post-carry', ['post' => $post], 'Weitertragen');
    }
}
