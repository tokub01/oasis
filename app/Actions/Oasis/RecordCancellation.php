<?php

namespace App\Actions\Oasis;

use App\Mail\Oasis\CancellationReceipt;
use App\Models\CancellationRequest;
use Illuminate\Support\Facades\Mail;

/**
 * Stores a § 312k BGB cancellation and confirms it promptly in text form: one receipt to the member, one copy to
 * the operator. A mail failure propagates (no success is shown), while the stored declaration remains for follow-up.
 */
class RecordCancellation
{
    /** @param  array<string, mixed>  $declaration  validated CancellationForm data */
    public function handle(array $declaration): CancellationRequest
    {
        $submission = CancellationRequest::create(collect($declaration)->only([
            'name', 'email', 'username', 'contract', 'type', 'reason', 'timing', 'effective_date',
        ])->all());

        Mail::to($submission->email)->send(new CancellationReceipt($submission));
        Mail::to(config('oasis.mail.operator'))->send(new CancellationReceipt($submission, operatorCopy: true));

        return $submission;
    }
}
