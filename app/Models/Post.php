<?php

namespace App\Models;

use App\Enums\Network\Visibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A "Tropfen": a short post with text and up to four images. It can be a reply (`reply_to_id`) or carry another
 * Tropfen further with own words (`carried_post_id`, "weitertragen"), and can live in an oasis.
 *
 * Visibility (single source of truth: {@see scopeVisibleTo()} / {@see isVisibleTo()}):
 * - public Tropfen are readable by guests, members-only Tropfen only by verified members;
 * - a Tropfen a host removed from an oasis is only visible to its author.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $oasis_id
 * @property int|null $reply_to_id
 * @property int|null $carried_post_id
 * @property string $body
 * @property Visibility $visibility
 * @property Carbon|null $removed_from_oasis_at
 * @property Carbon $created_at
 * @property User $author
 * @property Oasis|null $oasis
 * @property Post|null $carried
 * @property Post|null $replyTo
 */
class Post extends Model
{
    /** Maximum characters of a Tropfen ("kurz gesagt"). */
    public const MAX_LENGTH = 500;

    /** Maximum images per Tropfen. */
    public const MAX_IMAGES = 4;

    protected $fillable = ['user_id', 'oasis_id', 'reply_to_id', 'carried_post_id', 'body', 'visibility'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['visibility' => Visibility::class, 'removed_from_oasis_at' => 'datetime'];
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
                ->when(! $member, fn (Builder $guest) => $guest->where('visibility', Visibility::Public->value)))
            ->when($viewer !== null, fn (Builder $own) => $own->orWhere('user_id', $viewer->id)));
    }

    public function isVisibleTo(?User $viewer): bool
    {
        if ($viewer !== null && $viewer->id === $this->user_id) {
            return true;
        }
        if ($this->removed_from_oasis_at !== null) {
            return false;
        }

        return $this->visibility === Visibility::Public || $viewer?->hasVerifiedEmail() === true;
    }
}
