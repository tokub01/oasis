<?php

namespace App\Models;

use App\Enums\Network\OasisRole;
use App\Enums\Network\Visibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An oasis: a themed space with hosts and its own house rules. Following an oasis makes you a member: its Tropfen
 * appear in your Quelle and you may post into it. Hosts edit the oasis and remove Tropfen that break the house rules.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $description
 * @property string $house_rules
 * @property Visibility $visibility
 * @property string $color
 * @property int|null $created_by
 */
class Oasis extends Model
{
    protected $table = 'oases';

    protected $fillable = ['slug', 'name', 'description', 'house_rules', 'visibility', 'color', 'created_by'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['visibility' => Visibility::class];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'oasis_members')->withPivot(['role', 'joined_at']);
    }

    /** @return BelongsToMany<User, $this> */
    public function hosts(): BelongsToMany
    {
        return $this->members()->wherePivot('role', OasisRole::Host->value);
    }

    /** @return HasMany<Post, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function isVisibleTo(?User $viewer): bool
    {
        return $this->visibility === Visibility::Public || $viewer?->hasVerifiedEmail() === true;
    }

    public function isHost(?User $user): bool
    {
        return $user !== null && $this->hosts()->whereKey($user->id)->exists();
    }

    public function isMember(?User $user): bool
    {
        return $user !== null && $this->members()->whereKey($user->id)->exists();
    }
}
