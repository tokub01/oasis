<?php

namespace App\Http\Controllers\Network;

use App\Actions\Network\DeletePost;
use App\Actions\Network\ManageOasis;
use App\Actions\Network\PublishPost;
use App\Http\Controllers\Controller;
use App\Http\Requests\Network\PostRequest;
use App\Models\Post;
use App\Queries\Network\PostFeed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** One Tropfen: read (also as guest), write, carry further, delete and – for hosts – remove from an oasis. */
class PostController extends Controller
{
    /** Tropfen page with its replies. Unknown and unreadable Tropfen are both 404, so nothing leaks. */
    public function show(Request $request, Post $post, PostFeed $feed): View
    {
        abort_unless(Gate::forUser($request->user())->allows('view', $post), 404);
        $post = $feed->withCardData(Post::whereKey($post->id), $request->user())->firstOrFail();

        return view('app.network.posts.show', ['post' => $post, 'replies' => $feed->replies($post, $request->user())]);
    }

    public function store(PostRequest $request, PublishPost $publish): RedirectResponse
    {
        // Resolved models replace the raw ids/slugs from the validated input.
        $post = $publish->handle($request->user(), [
            ...$request->validated(),
            'oasis' => $request->oasis(),
            'reply_to' => $request->replyTo(),
            'carried' => $request->carried(),
        ], $request->images());

        $target = $post->reply_to_id ? route('network.posts.show', $post->reply_to_id).'#tropfen-'.$post->id
            : ($post->oasis ? route('network.oases.show', $post->oasis) : route('app.home'));

        return redirect($target)->with('status', $post->reply_to_id ? 'Deine Antwort ist da.' : 'Dein Tropfen ist da.');
    }

    /** Form to carry a Tropfen further with own words. */
    public function carry(Request $request, Post $post): View
    {
        Gate::authorize('interact', $post);

        return view('app.network.posts.carry', ['post' => $post->load(['author.profile', 'images'])]);
    }

    public function destroy(Post $post, DeletePost $delete): RedirectResponse
    {
        Gate::authorize('delete', $post);
        $delete->handle($post);

        return redirect()->route('app.home')->with('status', 'Dein Tropfen ist gelöscht.');
    }

    public function removeFromOasis(Post $post, ManageOasis $oases): RedirectResponse
    {
        Gate::authorize('removeFromOasis', $post);
        $oases->removePost($post);

        return redirect()->route('network.oases.show', $post->oasis)
            ->with('status', 'Der Tropfen ist aus der Oase entfernt. Die Person sieht ihn weiterhin selbst.');
    }
}
