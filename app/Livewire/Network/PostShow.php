<?php

namespace App\Livewire\Network;

use App\Models\Post;
use App\Queries\Network\PostFeed;
use App\Support\Network\Mentions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;

/** Public-readable Tropfen detail and replies; writes recheck policies and leave newly unreadable pages. */
class PostShow extends NetworkPage
{
    #[Locked]
    public int $postId;

    /** Complete the authorized action, then leave a page whose target is no longer readable. */
    public function deletePost(int $postId): void
    {
        parent::deletePost($postId);
        $this->redirectRoute('app.home', navigate: true);
    }

    /** Blocking an author removes the current target from the reader's visible network. */
    public function blockUser(int $userId): void
    {
        parent::blockUser($userId);
        $this->redirectRoute('app.home', navigate: true);
    }

    /** A removed oasis post is no longer readable, so leave the page after the authorized removal. */
    public function removePostFromOasis(int $postId): void
    {
        parent::removePostFromOasis($postId);
        $this->redirectRoute('app.home', navigate: true);
    }

    /** Bind the route target once; the identifier cannot be changed by a browser update. */
    public function mount(Post $post): void
    {
        $this->postId = $post->id;
    }

    /** Recheck visibility for guests and members whenever an interaction refreshes the page. */
    public function render(): View
    {
        $post = Post::findOrFail($this->postId);
        abort_unless(Gate::allows('view', $post), 404);
        $feed = app(PostFeed::class);
        $post = $feed->withCardData(Post::whereKey($post->id), auth()->user())->firstOrFail();

        Mentions::preloadPosts([$post]);

        return $this->page('livewire.network.post-show', ['post' => $post, 'replies' => $feed->replies($post, auth()->user())],
            'Tropfen von '.$post->author->display_name, public: true, noindex: ! $post->author->privacy()->indexable());
    }
}
