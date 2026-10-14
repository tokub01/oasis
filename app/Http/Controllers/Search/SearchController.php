<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Queries\Network\PostFeed;
use App\Queries\Search\DirectorySearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Search for members (/app/suche) and the public #Thema pages (/themen/{tag}). Results are chronological or
 * alphabetical – nothing is ranked by clicks or reach.
 */
class SearchController extends Controller
{
    /** Shortest term worth searching for. */
    public const MIN_LENGTH = 2;

    /** Characters of a #Thema: letters (incl. umlauts), digits and underscore, 2–40 long. */
    public const TAG_PATTERN = '[\p{L}\p{N}_]{2,40}';

    /**
     * The search page: people and oases in the side column, matching Tropfen newest first. A term that is exactly
     * one #Thema redirects to its topic page; shorter terms than MIN_LENGTH show the empty page with guidance.
     *
     * @return View|RedirectResponse the results page, or a redirect to search.tags.show
     */
    public function index(Request $request, DirectorySearch $directory, PostFeed $feed): View|RedirectResponse
    {
        $input = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $term = trim((string) ($input['q'] ?? ''));
        $viewer = $request->user();

        // A term that is exactly one #Thema goes straight to the topic list.
        if (preg_match('/^#('.self::TAG_PATTERN.')$/u', $term, $tag)) {
            return redirect()->route('search.tags.show', mb_strtolower($tag[1]));
        }

        $searching = mb_strlen($term) >= self::MIN_LENGTH;

        return view('app.search.index', [
            'term' => $term,
            'searching' => $searching,
            'people' => $searching ? $directory->people($term, $viewer) : collect(),
            'oases' => $searching ? $directory->oases($term) : collect(),
            'posts' => $searching ? $feed->matching($term, $viewer) : null,
        ]);
    }

    /**
     * Tropfen with one #Thema, newest first; guests see public Tropfen only.
     *
     * @param  string  $tag  topic without "#"; anything outside TAG_PATTERN is a 404
     * @return View the topic page with a cursor-paginated feed
     */
    public function tag(Request $request, string $tag, PostFeed $feed): View
    {
        abort_unless(preg_match('/^'.self::TAG_PATTERN.'$/u', $tag) === 1, 404);
        $tag = mb_strtolower($tag);

        return view('app.search.tag', ['tag' => $tag, 'posts' => $feed->tagged($tag, $request->user())]);
    }
}
