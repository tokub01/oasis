<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A personal invitation link ("Gemeinsam starten"). Each member may create `oasis.invites_per_month` links per
 * calendar month. Whoever registers through a link and the inviter follow each other automatically.
 *
 * @property int $id
 * @property int $inviter_id
 * @property string $token
 * @property int|null $accepted_by
 * @property Carbon|null $accepted_at
 * @property Carbon $expires_at
 * @property Carbon $created_at
 * @property User $inviter
 */
class Invitation extends Model
{
    /** Days an invitation link stays valid. */
    public const VALID_DAYS = 30;

    protected $fillable = ['inviter_id', 'token', 'expires_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['accepted_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    /**
     * Links that can still be used.
     *
     * @param  Builder<Invitation>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }
}
