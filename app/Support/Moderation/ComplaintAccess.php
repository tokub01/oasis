<?php

namespace App\Support\Moderation;

use App\Models\ContentReport;
use App\Models\ModerationDecision;
use App\Models\User;

/**
 * Resolve who complains about a decision without accepting emails or account ids from a public form.
 *
 * The complaint form (App\Livewire\Moderation\ComplaintForm) knows only a recipient selector: "reporter" or
 * "subject" from the signed link in the decision mail (the `signed` route middleware checks the link before the
 * form is shown), or "account" for the signed-in affected member. The email address is looked up here,
 * server-side, every time it is needed.
 */
class ComplaintAccess
{
    /** The stored email address behind the selector, or 403 when it does not apply to this decision. */
    public function recipient(ModerationDecision $decision, string $selector, ?User $user): string
    {
        $case = $decision->case;
        $email = match ($selector) {
            'reporter' => $case->source instanceof ContentReport ? $case->source->email : null,
            'subject' => $case->subjectUser?->email,
            'account' => $user !== null && $user->id === $case->subject_user_id ? $user->email : null,
            default => null,
        };
        abort_unless($email !== null, 403);

        return $email;
    }
}
