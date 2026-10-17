<?php

namespace App\Actions\Moderation;

use App\Enums\Moderation\Outcome;
use App\Models\AccountStrike;
use App\Models\AccountSuspension;
use App\Models\ModerationDecision;
use Illuminate\Support\Str;

/**
 * Account consequences of a human moderation decision (DecideCase, ReviewComplaint), docs/oasis-moderation.md:
 * - "Keine Maßnahme": a lock from this case ends, a strike from this case is overturned, and a ban ends when fewer
 *   than `strikes_for_ban` strikes remain;
 * - any other outcome: one strike for this case; the provisional lock ends (the violation is recorded, the member may
 *   write again), unless the outcome is "Konto gesperrt", which keeps the account locked until staff release it;
 * - at `strikes_for_ban` strikes the account is banned permanently.
 * The operator account (User::isSuperAdmin) is never affected.
 */
class ApplyAccountConsequences
{
    public function __construct(private readonly SuspendAccount $suspend, private readonly LiftSuspension $lift) {}

    public function handle(ModerationDecision $decision): void
    {
        $case = $decision->case;
        $user = $case->subjectUser;
        if ($user === null || $user->isSuperAdmin()) {
            return;
        }
        $fromCase = AccountSuspension::active()->where('user_id', $user->id)->where('moderation_case_id', $case->id)
            ->where('type', '!=', AccountSuspension::BAN)->get();

        if ($decision->outcome === Outcome::NoAction) {
            AccountStrike::where('moderation_case_id', $case->id)->whereNull('overturned_at')->update(['overturned_at' => now()]);
            $fromCase->each(fn (AccountSuspension $lock) => $this->lift->handle($lock, null, 'released'));
            $ban = AccountSuspension::active()->where('user_id', $user->id)->where('type', AccountSuspension::BAN)->first();
            if ($ban !== null && $user->activeStrikeCount() < $this->limit()) {
                $this->lift->handle($ban, null, 'overturned');
            }

            return;
        }

        AccountStrike::updateOrCreate(['moderation_case_id' => $case->id], ['user_id' => $user->id, 'overturned_at' => null]);
        if ($user->activeStrikeCount() >= $this->limit()) {
            $this->suspend->ban($user, $case, $this->limit());

            return;
        }
        if ($decision->outcome === Outcome::AccountSuspended) {
            $this->suspend->byDecision($user, $case, 'Das Moderationsteam hat einen Verstoß gegen die Community-Regeln festgestellt: '
                .Str::limit(trim((string) $decision->facts), 400).' Grundlage: '.trim((string) $decision->rule_reference).'.');

            return;
        }
        $fromCase->each(fn (AccountSuspension $lock) => $this->lift->handle($lock, null, 'confirmed'));
    }

    private function limit(): int
    {
        return max(1, (int) config('oasis.moderation.strikes_for_ban', 3));
    }
}
