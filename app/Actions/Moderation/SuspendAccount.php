<?php

namespace App\Actions\Moderation;

use App\Mail\Moderation\AccountSuspendedNotice;
use App\Models\AccountSuspension;
use App\Models\ModerationCase;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Locks an account (docs/oasis-moderation.md, "Sperren"). Only the operator account (User::isSuperAdmin) is never
 * locked; moderators and other admins are treated like members. One active
 * lock per account: a second trigger while locked adds nothing. Every new lock is explained to the member by mail
 * (Art. 17 DSA statement of reasons) and on /app/gesperrt, where they may answer.
 */
class SuspendAccount
{
    /**
     * At once when the automated check holds a Tropfen: provisional until a person in the moderation team decides.
     *
     * @param  string  $finding  what the check found, e.g. "eine Beleidigung" (ContentVerdict::holdReason)
     * @param  string  $modelReason  the model's short German reason, may be empty
     */
    public function provisional(Post $post, string $finding, string $modelReason = ''): ?AccountSuspension
    {
        $user = $post->author;
        if ($user->isSuperAdmin()) {
            return null;
        }
        $active = $user->activeSuspension();
        if ($active !== null) {
            return $active;
        }

        $accusation = 'Verdacht auf '.$finding.' in deinem Tropfen vom '
            .$post->created_at->timezone('Europe/Berlin')->format('d.m.Y, H:i').' Uhr (Community-Regeln).';
        if (trim($modelReason) !== '') {
            $accusation .= ' Begründung der automatischen Prüfung: „'.trim($modelReason).'“';
        }

        return $this->lock($user, AccountSuspension::PROVISIONAL, $accusation, null, $post);
    }

    /** The case opened by the background check belongs to the provisional lock of the same Tropfen. */
    public function attachCase(Post $post, ModerationCase $case): void
    {
        AccountSuspension::active()->where('post_id', $post->id)->whereNull('moderation_case_id')
            ->update(['moderation_case_id' => $case->id]);
    }

    /** From a moderation decision "Konto gesperrt": locked until staff release the account. */
    public function byDecision(User $user, ModerationCase $case, string $accusation): AccountSuspension
    {
        $active = $user->activeSuspension();
        if ($active !== null) {
            $active->update(['type' => AccountSuspension::SUSPENSION, 'moderation_case_id' => $active->moderation_case_id ?? $case->id]);

            return $active;
        }

        return $this->lock($user, AccountSuspension::SUSPENSION, $accusation, $case);
    }

    /** After the configured number of confirmed violations: ends any other lock and bans the account. */
    public function ban(User $user, ModerationCase $case, int $strikes): AccountSuspension
    {
        AccountSuspension::active()->where('user_id', $user->id)->where('type', '!=', AccountSuspension::BAN)
            ->update(['ended_at' => now(), 'result' => 'confirmed']);
        $existing = AccountSuspension::active()->where('user_id', $user->id)->where('type', AccountSuspension::BAN)->first();

        return $existing ?? $this->lock($user, AccountSuspension::BAN,
            'Das Moderationsteam hat '.$strikes.' Verstöße gegen die Community-Regeln bestätigt. Nach '.$strikes
            .' bestätigten Verstößen sperren wir ein Konto dauerhaft.', $case);
    }

    private function lock(User $user, string $type, string $accusation, ?ModerationCase $case = null, ?Post $post = null): AccountSuspension
    {
        $suspension = AccountSuspension::create([
            'user_id' => $user->id, 'moderation_case_id' => $case?->id, 'post_id' => $post?->id, 'type' => $type,
            'accusation' => $accusation, 'excerpt' => $post === null ? null : Str::limit($post->body, 500),
            'started_at' => now(),
        ]);
        Mail::to($user->email)->send(new AccountSuspendedNotice($suspension));

        return $suspension;
    }
}
