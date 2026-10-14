<?php

namespace App\Queries\Search;

use App\Models\Oasis;
use App\Models\User;
use App\Queries\Network\PostFeed;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Finds people and oases for the search page. No ranking by popularity: exact handle matches first, then
 * alphabetical. The search page is for verified members only, and verified members may open every profile and every
 * oasis (public or members-only), so visibility needs no extra filter; blockades are excluded in SQL so the limit
 * always applies to results the viewer may see.
 */
class DirectorySearch
{
    public const LIMIT = 12;

    /**
     * People whose username or display name contains the term; a leading "@" is ignored for the username.
     *
     * @param  string  $term  trimmed search term (at least SearchController::MIN_LENGTH characters)
     * @param  User  $viewer  the verified member searching; accounts with a blockade in either direction are left out
     * @return Collection<int, User> at most LIMIT verified accounts, exact username first, then by display name
     */
    public function people(string $term, User $viewer): Collection
    {
        $handle = ltrim(mb_strtolower($term), '@');

        return User::query()
            ->whereNotNull('email_verified_at')
            ->whereNotIn('id', $viewer->blockedUserIds())
            ->where(function (Builder $query) use ($term, $handle): void {
                $query->where('display_name', 'like', '%'.PostFeed::escapeLike($term).'%');
                // "@@" leaves an empty handle, which would match every username – search names only then.
                if ($handle !== '') {
                    $query->orWhere('username', 'like', '%'.PostFeed::escapeLike($handle).'%');
                }
            })
            ->with('profile')
            ->orderByRaw('username = ? desc', [$handle])
            ->orderBy('display_name')
            ->limit(self::LIMIT)
            ->get();
    }

    /**
     * Oases whose name or description contains the term.
     *
     * @param  string  $term  trimmed search term (at least SearchController::MIN_LENGTH characters)
     * @return Collection<int, Oasis> at most LIMIT oases with members_count, alphabetical
     */
    public function oases(string $term): Collection
    {
        $like = '%'.PostFeed::escapeLike($term).'%';

        return Oasis::query()
            ->where(fn (Builder $query) => $query->where('name', 'like', $like)->orWhere('description', 'like', $like))
            ->withCount('members')
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get();
    }
}
