<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Reaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Quiet reactions: one per member and Tropfen, counted only for the author. */
class ReactionController extends Controller
{
    public function store(Request $request, Post $post): RedirectResponse
    {
        Gate::authorize('interact', $post);
        Reaction::firstOrCreate(['post_id' => $post->id, 'user_id' => $request->user()->id]);

        return back()->with('status', 'Reaktion gesendet. Nur die Person sieht, wie viele reagiert haben.');
    }

    /** Taking a reaction back is always allowed, also for read-only accounts. */
    public function destroy(Request $request, Post $post): RedirectResponse
    {
        Reaction::where('post_id', $post->id)->where('user_id', $request->user()->id)->delete();

        return back()->with('status', 'Reaktion zurückgenommen.');
    }
}
