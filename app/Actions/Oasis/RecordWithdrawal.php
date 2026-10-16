<?php

namespace App\Actions\Oasis;

use App\Mail\Oasis\WithdrawalReceipt;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\Mail;

/**
 * Stores a withdrawal (§§ 355 ff. BGB) and sends the receipt to the member and a copy to the operator. A mail failure
 * propagates (no success is shown), while the stored declaration remains for follow-up.
 */
class RecordWithdrawal
{
    /** @param  array<string, mixed>  $declaration  validated WithdrawalForm data */
    public function handle(array $declaration): WithdrawalRequest
    {
        $submission = WithdrawalRequest::create(collect($declaration)->only(['name', 'email', 'contract', 'contracted_on'])->all());

        Mail::to($submission->email)->send(new WithdrawalReceipt($submission));
        Mail::to(config('oasis.mail.operator'))->send(new WithdrawalReceipt($submission, operatorCopy: true));

        return $submission;
    }
}
