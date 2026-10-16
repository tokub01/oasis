<?php

namespace App\Actions\Oasis;

use App\Mail\Oasis\ContentReportNotice;
use App\Mail\Oasis\ContentReportReceipt;
use App\Models\ContentReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Records a DSA Art. 16 notice with the time of the good-faith declaration, acknowledges it to the reporter and
 * notifies the moderation team. Storing only acknowledges receipt; the notice still needs a human decision.
 */
class RecordContentReport
{
    /** @param  array<string, mixed>  $notice  validated ContentReportForm data */
    public function handle(array $notice): ContentReport
    {
        $submission = DB::transaction(fn () => ContentReport::create([
            ...collect($notice)->only(['url', 'reason', 'name', 'email'])->all(),
            'truthful_confirmed_at' => now(),
        ]));

        Mail::to($submission->email)->send(new ContentReportReceipt($submission));
        Mail::to(config('oasis.mail.moderation'))->send(new ContentReportNotice($submission));

        return $submission;
    }
}
