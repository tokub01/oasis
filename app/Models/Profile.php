<?php

namespace App\Models;

use App\Enums\Network\AvatarColor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Public-facing profile of an account (stage 2). Kept apart from `users` so account/billing columns and profile
 * content evolve independently. Every account has one lazily: see {@see Concerns\HasNetwork::profileOrDefault()}.
 *
 * @property int $user_id
 * @property string|null $bio
 * @property string|null $location
 * @property AvatarColor $avatar_color
 * @property User $user
 */
class Profile extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = ['bio', 'location', 'avatar_color'];

    /** New profiles use the default avatar; audience lives in the privacy settings. */
    protected $attributes = ['avatar_color' => 'sun'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['avatar_color' => AvatarColor::class];
    }

    /**
     * The account this profile belongs to (moderation links to the profile by username).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Whether a viewer (null = guest) may open this profile. */
    public function isVisibleTo(?User $viewer): bool
    {
        return ! ($viewer?->isBlockedWith($this->user) ?? false)
            && $this->user->privacy()->allows('profile_audience', $viewer);
    }
}
