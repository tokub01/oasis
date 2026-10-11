<?php

namespace App\Actions\Network;

use App\Enums\Network\OasisRole;
use App\Models\Oasis;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creating and running an oasis: the founder becomes its first host and follower; hosts edit it, appoint further
 * hosts among its members and remove Tropfen that break the house rules.
 */
class ManageOasis
{
    public function __construct(private readonly ManageFollows $follows) {}

    /** @param array{name: string, description: string, house_rules: string, visibility: string, color: string} $input */
    public function create(User $founder, array $input): Oasis
    {
        return DB::transaction(function () use ($founder, $input): Oasis {
            $oasis = Oasis::create($input + ['slug' => $this->uniqueSlug($input['name']), 'created_by' => $founder->id]);
            $this->follows->follow($founder, $oasis);
            $oasis->members()->updateExistingPivot($founder->id, ['role' => OasisRole::Host->value]);

            return $oasis;
        });
    }

    /** @param array{name: string, description: string, house_rules: string, visibility: string, color: string} $input */
    public function update(Oasis $oasis, array $input): Oasis
    {
        $oasis->update($input);

        return $oasis;
    }

    /** Make a member a host (or back to member). The last host cannot step down. */
    public function setRole(Oasis $oasis, User $member, OasisRole $role): void
    {
        if (! $oasis->isMember($member)) {
            throw ValidationException::withMessages(['role' => 'Nur Mitglieder der Oase können Gastgeber:in werden.']);
        }
        if ($role === OasisRole::Member && $oasis->isHost($member) && $oasis->hosts()->count() === 1) {
            throw ValidationException::withMessages(['role' => 'Eine Oase braucht mindestens eine Gastgeber:in.']);
        }
        $oasis->members()->updateExistingPivot($member->id, ['role' => $role->value]);
    }

    /** Host action: the Tropfen disappears from the oasis and from everyone's view except its author's. */
    public function removePost(Post $post): void
    {
        $post->forceFill(['removed_from_oasis_at' => now()])->save();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name, '-', 'de'), 50, '') ?: 'oase';
        $slug = $base;
        for ($suffix = 2; Oasis::where('slug', $slug)->exists(); $suffix++) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }
}
