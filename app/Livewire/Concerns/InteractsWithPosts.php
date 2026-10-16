<?php

namespace App\Livewire\Concerns;

use App\Actions\Network\DeletePost;
use App\Actions\Network\ManageOasis;
use App\Actions\Network\ManageReaction;
use App\Actions\Safety\ManageBlock;
use App\Actions\Safety\ManageBookmark;
use App\Actions\Safety\ManageMute;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;

/**
 * Shared, independently authorized actions for x-network.post-card in network, search and saved-post lists.
 * Surrounding components render postStatus with role="status" and query their lists again after each action.
 * Action IDs are untrusted arguments: records and current permissions are resolved on every invocation.
 */
trait InteractsWithPosts
{
    #[Locked]
    public string $postStatus = '';

    /** React only to a currently readable post with an active participation entitlement. */
    public function react(int $postId): void
    {
        $user = $this->postActor();
        $post = Post::findOrFail($postId);
        Gate::authorize('interact', $post);
        app(ManageReaction::class)->react($user, $post);
        $this->postStatus = 'Reaktion gesendet. Nur die Person sieht, wie viele reagiert haben.';
    }

    /** Withdraw an own reaction, including after paid participation has expired. */
    public function unreact(int $postId): void
    {
        $user = $this->postActor();
        app(ManageReaction::class)->remove($user, Post::findOrFail($postId));
        $this->postStatus = 'Reaktion zurückgenommen.';
    }

    /** Save a visible post privately; ManageBookmark repeats the visibility check. */
    public function bookmark(int $postId): void
    {
        $user = $this->postActor();
        $post = Post::findOrFail($postId);
        abort_unless(Gate::forUser($user)->allows('view', $post), 404);
        app(ManageBookmark::class)->bookmark($user, $post);
        $this->postStatus = 'Tropfen gemerkt.';
    }

    /** Remove only an own bookmark even if its post has since become private. */
    public function unbookmark(int $postId): void
    {
        $user = $this->postActor();
        app(ManageBookmark::class)->remove($user, Post::findOrFail($postId));
        $this->postStatus = 'Tropfen aus der Merkliste entfernt.';
    }

    /** Verified accounts may protect themselves by blocking; self-blocks are rejected by the action. */
    public function blockUser(int $userId): void
    {
        $user = $this->postActor();
        app(ManageBlock::class)->block($user, User::findOrFail($userId));
        $this->postStatus = 'Die Person ist blockiert. Ihr seht einander nicht mehr.';
    }

    /** Verified accounts may mute a person privately, including in read-only mode. */
    public function muteUser(int $userId): void
    {
        $user = $this->postActor();
        app(ManageMute::class)->mute($user, User::findOrFail($userId));
        $this->postStatus = 'Die Person ist stummgeschaltet. Sie merkt nichts.';
    }

    /** Undo only the current account's mute, including in read-only mode. */
    public function unmuteUser(int $userId): void
    {
        $user = $this->postActor();
        app(ManageMute::class)->unmute($user, User::findOrFail($userId));
        $this->postStatus = 'Stummschaltung aufgehoben.';
    }

    /** Authors may delete only their own posts, including in read-only mode. */
    public function deletePost(int $postId): void
    {
        $this->postActor();
        $post = Post::findOrFail($postId);
        Gate::authorize('delete', $post);
        app(DeletePost::class)->handle($post);
        $this->postStatus = 'Dein Tropfen ist gelöscht.';
    }

    /** Only current oasis hosts may remove an existing post from their oasis. */
    public function removePostFromOasis(int $postId): void
    {
        $this->postActor();
        $post = Post::findOrFail($postId);
        Gate::authorize('removeFromOasis', $post);
        app(ManageOasis::class)->removePost($post);
        $this->postStatus = 'Der Tropfen ist aus der Oase entfernt. Die Person sieht ihn weiterhin selbst.';
    }

    /** Re-authorize authentication and verification on every action, including Livewire update requests. */
    private function postActor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->hasVerifiedEmail(), 403);

        return $user;
    }
}
