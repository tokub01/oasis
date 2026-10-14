<?php

namespace App\Queries\Safety;

use App\Models\Bookmark;
use App\Models\Post;
use App\Models\User;
use App\Queries\Network\PostFeed;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Loads the private bookmark list of a member, newest bookmarks first.
 *
 * Unreadable Tropfen (deleted posts, removed from oasis, or from authors
 * who are blocked or have blocked the viewer, or are muted) do not appear.
 */
class BookmarkList
{
    public const PER_PAGE = 20;

    /**
     * Paginate bookmarked Tropfen, newest bookmark first.
     *
     * @return CursorPaginator<int, Post>
     */
    public function paginate(User $viewer): CursorPaginator
    {
        $blocked = $viewer->blockedUserIds();
        $muted = $viewer->mutedUserIds();

        $userBookmarks = Bookmark::query()
            ->select(['id as bookmark_id', 'post_id'])
            ->where('user_id', $viewer->id);

        /** @var Builder<Post> $query */
        $query = Post::query()
            ->select(['posts.*', 'user_bookmarks.bookmark_id'])
            ->joinSub($userBookmarks, 'user_bookmarks', 'posts.id', '=', 'user_bookmarks.post_id')
            ->visibleTo($viewer)
            ->when(! empty($blocked), fn (Builder $q) => $q->whereNotIn('posts.user_id', $blocked))
            ->when(! empty($muted), fn (Builder $q) => $q->whereNotIn('posts.user_id', $muted))
            ->orderByDesc('user_bookmarks.bookmark_id');

        (new PostFeed)->withCardData($query, $viewer);

        /** @var CursorPaginator<int, Post> $paginator */
        $paginator = $query->cursorPaginate(self::PER_PAGE);

        return $paginator;
    }
}
