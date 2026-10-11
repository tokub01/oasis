<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Conversation metadata and current key state used by authorized chat APIs. */
class Conversation extends Model
{
    protected $guarded = [];

    /**
     * Versions, lifetime and rotation flags are JSON numbers/booleans.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['key_version' => 'integer', 'lifetime_days' => 'integer', 'rotation_required' => 'boolean'];
    }

    /**
     * Include past memberships where historical sender verification needs them.
     *
     * @return HasMany<ConversationMember, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(ConversationMember::class);
    }

    /**
     * Restrict channel and API access to members who have not left.
     *
     * @return HasMany<ConversationMember, $this>
     */
    public function activeMembers(): HasMany
    {
        return $this->memberships()->whereNull('left_at');
    }

    /** Return the caller's active membership or fail closed without exposing metadata. */
    public function requireMember(User $user, bool $owner = false): ConversationMember
    {
        $member = $this->activeMembers()->where('user_id', $user->id)->first();
        abort_unless($member && (! $owner || $member->role === 'owner'), 403);

        return $member;
    }

    /**
     * Stable metadata contract; never serialize identities or grants by model relationships.
     *
     * @return array<string, mixed>
     */
    public function apiData(): array
    {
        return [
            'id' => (string) $this->id, 'type' => $this->type, 'title' => $this->title,
            'created_by' => (string) $this->created_by, 'lifetime_days' => $this->lifetime_days,
            'key_version' => $this->key_version, 'rotation_required' => $this->rotation_required,
            'members' => $this->activeMembers()->with('user')->get()->map(fn ($member) => [
                'user_id' => (string) $member->user_id, 'username' => $member->user->username,
                'display_name' => $member->user->display_name, 'role' => $member->role,
                'joined_at' => $member->joined_at->toISOString(),
            ])->all(),
            'server_time' => now()->utc()->format('Y-m-d\\TH:i:s\\Z'),
        ];
    }
}
