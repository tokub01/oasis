<?php

namespace App\Actions\Network;

use App\Actions\Notifications\SendHint;
use App\Enums\Notifications\NoticeKind;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Personal invitation links ("Gemeinsam starten"): a monthly budget per member (`oasis.invites_per_month`), and on
 * acceptance inviter and new member follow each other.
 */
class ManageInvitations
{
    public function __construct(private readonly ManageFollows $follows) {}

    /** Links created in the current calendar month. */
    public function usedThisMonth(User $inviter): int
    {
        return $inviter->invitations()->where('created_at', '>=', now()->startOfMonth())->count();
    }

    public function remainingThisMonth(User $inviter): int
    {
        return max(0, (int) config('oasis.invites_per_month') - $this->usedThisMonth($inviter));
    }

    /** @throws ValidationException when the monthly budget is used up */
    public function create(User $inviter): Invitation
    {
        if ($this->remainingThisMonth($inviter) === 0) {
            throw ValidationException::withMessages(['invitation' => 'Diesen Monat hast du alle Einladungen verschickt.']);
        }

        return $inviter->invitations()->create([
            'token' => Str::random(40),
            'expires_at' => now()->addDays(Invitation::VALID_DAYS),
        ]);
    }

    /** Accept an open link for a newly registered member. Unknown, used or expired tokens are ignored. */
    public function accept(string $token, User $member): bool
    {
        $invitation = Invitation::open()->where('token', $token)->first();
        if ($invitation === null || $invitation->inviter_id === $member->id) {
            return false;
        }

        $invitation->forceFill(['accepted_by' => $member->id, 'accepted_at' => now()])->save();
        $this->follows->follow($member, $invitation->inviter);
        $this->follows->follow($invitation->inviter, $member);

        app(SendHint::class)->handle($invitation->inviter, $member,
            NoticeKind::Invitation, 'invitation:'.$invitation->id,
            ids: ['invitation_id' => $invitation->id]);

        return true;
    }
}
