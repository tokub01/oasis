<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A friend request (accepted_at null) or a friendship (docs/oasis-network.md, "Freundschaften"). Independent of
 * follows: friends do not follow each other automatically, and following needs no friendship. One row per pair and
 * direction; App\Actions\Network\ManageFriendship keeps at most one row per pair.
 *
 * @property int $id
 * @property int $requester_id
 * @property int $addressee_id
 * @property Carbon|null $accepted_at
 * @property Carbon $created_at
 * @property User $requester
 * @property User $addressee
 */
class Friendship extends Model
{
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /** @return BelongsTo<User, $this> */
    public function addressee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'addressee_id');
    }

    /**
     * The row between two people, in either direction.
     *
     * @param  Builder<Friendship>  $query
     */
    public function scopeBetween(Builder $query, User $one, User $other): void
    {
        $query->where(fn (Builder $pair) => $pair
            ->where(fn (Builder $a) => $a->where('requester_id', $one->id)->where('addressee_id', $other->id))
            ->orWhere(fn (Builder $b) => $b->where('requester_id', $other->id)->where('addressee_id', $one->id)));
    }
}
