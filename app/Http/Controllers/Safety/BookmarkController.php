<?php

namespace App\Http\Controllers\Safety;

use App\Actions\Safety\ManageBookmark;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Queries\Safety\BookmarkList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Private bookmarks: listing saved Tropfen and adding/removing them.
 */
class BookmarkController extends Controller
{
    /**
     * Show the member's private bookmark list, newest bookmarks first.
     */
    public function index(Request $request, BookmarkList $query): View
    {
        return view('app.safety.bookmarks', [
            'posts' => $query->paginate($request->user()),
        ]);
    }

    /**
     * Bookmark a Tropfen.
     */
    public function store(Request $request, Post $post, ManageBookmark $manage): RedirectResponse
    {
        $manage->bookmark($request->user(), $post);

        return back()->with('status', 'Tropfen gemerkt.');
    }

    /**
     * Remove a bookmark.
     */
    public function destroy(Request $request, Post $post, ManageBookmark $manage): RedirectResponse
    {
        $manage->remove($request->user(), $post);

        return back()->with('status', 'Tropfen aus der Merkliste entfernt.');
    }
}
