<?php

namespace App\Livewire\Network;

use App\Actions\Network\EditPost;
use App\Models\Post;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;

/**
 * Edit the text of an own Tropfen on /tropfen/{post}/bearbeiten (route network.posts.edit) within Post::EDIT_MINUTES.
 * Saving goes through EditPost (policy, validation, revision, new check) and returns to the Tropfen.
 */
class PostEdit extends NetworkPage
{
    #[Locked]
    public int $postId;

    public string $body = '';

    public function mount(Post $post): void
    {
        Gate::authorize('update', $post);
        $this->postId = $post->id;
        $this->body = $post->body;
    }

    public function save(EditPost $edit): void
    {
        $post = Post::findOrFail($this->postId);
        $edit->handle($this->actor(), $post, $this->body);
        session()->flash('status', 'Dein Tropfen ist geändert.');
        $this->redirectRoute('network.posts.show', $post, navigate: true);
    }

    public function render(): View
    {
        $post = Post::with(['author.profile', 'images'])->findOrFail($this->postId);
        Gate::authorize('update', $post);

        return $this->page('livewire.network.post-edit', [
            'post' => $post,
            'until' => $post->created_at->addMinutes(Post::EDIT_MINUTES),
        ], 'Tropfen bearbeiten');
    }
}
