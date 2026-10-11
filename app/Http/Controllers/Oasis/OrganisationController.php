<?php

namespace App\Http\Controllers\Oasis;

use App\Http\Controllers\Controller;
use App\Http\Requests\Oasis\OrganisationFormRequest;
use App\Mail\Oasis\OrganisationNotice;
use App\Models\OrganisationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

class OrganisationController extends Controller
{
    public function store(OrganisationFormRequest $request): RedirectResponse
    {
        $submission = OrganisationRequest::create([...$request->safe()->only(['organisation', 'people', 'email'])]);

        Mail::to(config('oasis.mail.operator'))->send(new OrganisationNotice($submission));

        return redirect()->route('oasis.bestaetigung.gemeinsam')->with('oasis.submitted.gemeinsam', true);
    }
}
