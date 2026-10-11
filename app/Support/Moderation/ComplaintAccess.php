<?php

namespace App\Support\Moderation;

use App\Models\ContentReport;
use App\Models\ModerationDecision;
use Illuminate\Http\Request;

/** Resolve complaint identities without accepting emails or account ids from a public form. */
class ComplaintAccess
{
    /** Read a signed recipient selector or require the authenticated affected account. */
    public function recipient(Request $request, ModerationDecision $decision): string
    {
        $case = $decision->case;
        if ($request->routeIs('moderation.complaint.account*')) {
            abort_unless($request->user()?->id === $case->subject_user_id, 403);

            return $request->user()->email;
        }
        abort_unless($request->hasValidSignature(), 403);
        $email = match ($request->query('recipient')) {
            'reporter' => $case->source instanceof ContentReport ? $case->source->email : null,
            'subject' => $case->subjectUser?->email,
            default => null,
        };
        abort_unless($email !== null, 403);

        return $email;
    }
}
