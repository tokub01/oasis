<?php

namespace App\Models;

use App\Enums\Network\PostKind;
use App\Enums\Network\Visibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A "Tropfen": a short post with text and up to four images. It can be a reply (`reply_to_id`) or carry another
 * Tropfen further with own words (`carried_post_id`, "weitertragen"), and can live in an oasis.
 *
 * Visibility (single source of truth: {@see scopeVisibleTo()} / {@see isVisibleTo()}):
 * - public Tropfen are readable by guests, members-only Tropfen only by verified members;
 * - a Tropfen a host removed from an oasis, or one held by the automated check for review, is only visible to its
 *   author (and to staff, who review it).
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $oasis_id
 * @property int|null $reply_to_id
 * @property int|null $carried_post_id
 * @property string $body
 * @property Visibility $visibility
 * @property PostKind $kind
 * @property Carbon|null $removed_from_oasis_at
 * @property bool $has_more_replies
 * @property Carbon|null $held_at set while the automated check holds it for human review (stage 10)
 * @property Carbon $created_at
 * @property User $author
 * @property Oasis|null $oasis
 * @property Post|null $carried
 * @property-read Collection<int, Post> $conversationReplies
 * @property Post|null $replyTo
 */
class Post extends Model
{
    /** Maximum characters of a Tropfen ("kurz gesagt"). */
    public const MAX_LENGTH = 500;

    /** Maximum images per Tropfen. */
    public const MAX_IMAGES = 4;

    protected $fillable = ['user_id', 'oasis_id', 'reply_to_id', 'carried_post_id', 'body', 'visibility', 'kind'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'visibility' => Visibility::class,
            'kind' => PostKind::class,
            'removed_from_oasis_at' => 'datetime',
            'held_at' => 'datetime',
        ];
    }

    /** Whether this Tropfen is a question to one's followers ("Frage an meine Karawane"). */
    public function isQuestion(): bool
    {
        return $this->kind === PostKind::Question;
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Oasis, $this> */
    public function oasis(): BelongsTo
    {
        return $this->belongsTo(Oasis::class);
    }

    /** @return BelongsTo<Post, $this> */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'reply_to_id');
    }

    /** @return BelongsTo<Post, $this> */
    public function carried(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'carried_post_id');
    }

    /** @return HasMany<Post, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(Post::class, 'reply_to_id');
    }

    /** @return HasMany<PostImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(PostImage::class)->orderBy('position');
    }

    /** @return HasMany<Reaction, $this> */
    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }

    /** @return HasMany<Bookmark, $this> */
    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    /**
     * Restrict a query to the Tropfen a viewer (null = guest) may read.
     *
     * @param  Builder<Post>  $query
     */
    public function scopeVisibleTo(Builder $query, ?User $viewer): void
    {
        $member = $viewer?->hasVerifiedEmail() === true;
        $query->where(fn (Builder $visible) => $visible
            ->where(fn (Builder $shown) => $shown
                ->whereNull('removed_from_oasis_at')
                ->whereNull('held_at')
                ->when(! $member, fn (Builder $guest) => $guest->where('visibility', Visibility::Public->value)))
            ->when($viewer !== null, fn (Builder $own) => $own->orWhere('user_id', $viewer->id)));
    }

    public function isVisibleTo(?User $viewer): bool
    {
        if ($viewer !== null && in_array($this->user_id, $viewer->blockedUserIds(), true)) {
            return false;
        }
        if ($viewer !== null && $viewer->id === $this->user_id) {
            return true;
        }
        // Staff open reported, removed and held Tropfen from a moderation case (the queue links here).
        if ($viewer?->role?->isStaff()) {
            return true;
        }
        if ($this->removed_from_oasis_at !== null || $this->held_at !== null) {
            return false;
        }
        if ($this->reply_to_id !== null && $this->replyTo?->author?->hidesReplyFrom($this) === true) {
            return false;
        }

        return $this->visibility === Visibility::Public || $viewer?->hasVerifiedEmail() === true;
    }
}
