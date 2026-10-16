<?php

namespace App\Actions\Oasis;

use App\Mail\Oasis\WaitlistConfirmation;
use App\Models\WaitlistEntry;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Puts an address on the founding start list with recorded consent and asks for double opt-in. An existing entry is
 * kept unchanged (original consent time and token); a confirmed address gets no further mail. Callers respond the
 * same way in every case, so the form never reveals whether an address is already on the list.
 */
class JoinWaitlist
{
    /** @param  array<string, mixed>  $signup  validated WaitlistForm data with a normalised e-mail address */
    public function handle(array $signup): WaitlistEntry
    {
        $entry = WaitlistEntry::firstOrCreate(
            ['email' => $signup['email']],
            ['group' => $signup['group'] ?? null, 'consented_at' => now(), 'token' => Str::random(64)],
        );

        if ($entry->confirmed_at === null) {
            Mail::to($entry->email)->send(new WaitlistConfirmation($entry));
        }

        return $entry;
    }
}
