<?php

namespace App\Actions\Oasis;

use App\Mail\Oasis\OrganisationNotice;
use App\Models\OrganisationRequest;
use Illuminate\Support\Facades\Mail;

/** Stores an organisation's "Gemeinsam starten" enquiry and notifies the operator before receipt is confirmed. */
class RecordOrganisationEnquiry
{
    /** @param  array<string, mixed>  $enquiry  validated OrganisationForm data */
    public function handle(array $enquiry): OrganisationRequest
    {
        $submission = OrganisationRequest::create(collect($enquiry)->only(['organisation', 'people', 'email'])->all());

        Mail::to(config('oasis.mail.operator'))->send(new OrganisationNotice($submission));

        return $submission;
    }
}
