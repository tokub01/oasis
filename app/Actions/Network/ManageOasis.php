<?php

namespace App\Actions\Network;

use App\Actions\Notifications\SendHint;
use App\Enums\Network\OasisRole;
use App\Enums\Network\Visibility;
use App\Enums\Notifications\NoticeKind;
use App\Models\ContentCheck;
use App\Models\Follow;
use App\Models\Oasis;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creating and running an oasis: the founder becomes its first host and follower; hosts edit it (switching to
 * members-only also protects existing Tropfen), appoint further hosts, remove Tropfen that break the house rules and
 * close it. Before an account is deleted, its oases are handed over so none is left without a host.
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
        return DB::transaction(function () use ($oasis, $input): Oasis {
            $oasis->update($input);
            // Members-only means members-only for what was already written, too: every Tropfen in the oasis
            // (incl. replies, which carry the oasis id) follows. Switching back to public does not publish them again.
            if ($oasis->visibility === Visibility::Members) {
                Post::where('oasis_id', $oasis->id)->where('visibility', Visibility::Public->value)
                    ->update(['visibility' => Visibility::Members->value]);
            }

            return $oasis;
        });
    }

    /**
     * Close an oasis (hosts only, checked by the caller). Its Tropfen stay with their authors without an oasis
     * (`posts.oasis_id` is nulled by the foreign key); memberships, follows and the automated check of the oasis
     * (unless a moderation case is based on it) are removed.
     */
    public function close(Oasis $oasis): void
    {
        DB::transaction(function () use ($oasis): void {
            Follow::where('followable_type', $oasis->getMorphClass())->where('followable_id', $oasis->id)->delete();
            $oasis->delete();
            ContentCheck::orphaned()->whereMorphedTo('checkable', $oasis)->delete();
        });
    }

    /**
     * Before an account is deleted: in every oasis where it is the only host, the longest-standing other member
     * becomes host; an oasis without other members is closed. No oasis is left without a host.
     */
    public function handOverBeforeDeletion(User $leaving): void
    {
        $hosted = $leaving->oases()->wherePivot('role', OasisRole::Host->value)->get();
        foreach ($hosted as $oasis) {
            if ($oasis->hosts()->whereKeyNot($leaving->id)->exists()) {
                continue;
            }
            $successor = $oasis->members()->whereKeyNot($leaving->id)->orderBy('oasis_members.joined_at')->first();
            if ($successor === null) {
                $this->close($oasis);
            } else {
                $oasis->members()->updateExistingPivot($successor->id, ['role' => OasisRole::Host->value]);
            }
        }
    }

    /** Make a member a host (or back to member). The last host cannot step down. */
    public function setRole(Oasis $oasis, User $member, OasisRole $role): void
    {
        if (! $oasis->isMember($member)) {
            throw ValidationException::withMessages(['role' => 'Nur Mitglieder der Oase können Gastgeber:in werden.']);
        }
        if (auth()->user() instanceof User && auth()->user()->isBlockedWith($member)) {
            throw ValidationException::withMessages(['role' => 'Rollen können für blockierte Personen nicht geändert werden.']);
        }
        if ($role === OasisRole::Member && $oasis->isHost($member) && $oasis->hosts()->count() === 1) {
            throw ValidationException::withMessages(['role' => 'Eine Oase braucht mindestens eine Gastgeber:in.']);
        }
        // The conditional update also suppresses hints from concurrent, repeated promotions.
        $changed = $oasis->members()->wherePivot('role', '!=', $role->value)
            ->updateExistingPivot($member->id, ['role' => $role->value]);
        if ($changed && $role === OasisRole::Host && auth()->user() instanceof User) {
            app(SendHint::class)->handle($member, auth()->user(),
                NoticeKind::Host, 'host:'.Str::uuid(),
                ids: ['oasis_id' => $oasis->id]);
        }
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
