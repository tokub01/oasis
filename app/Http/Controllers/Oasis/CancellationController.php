<?php

namespace App\Http\Controllers\Oasis;

use App\Http\Controllers\Controller;
use App\Http\Requests\Oasis\CancellationFormRequest;
use App\Mail\Oasis\CancellationReceipt;
use App\Models\CancellationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

class CancellationController extends Controller
{
    public function store(CancellationFormRequest $request): RedirectResponse
    {
        $submission = CancellationRequest::create([...$request->safe()->only(['name', 'email', 'username', 'contract', 'type', 'reason', 'timing', 'effective_date'])]);

        Mail::to($submission->email)->send(new CancellationReceipt($submission));
        Mail::to(config('oasis.mail.operator'))->send(new CancellationReceipt($submission, operatorCopy: true));

        return redirect()->route('oasis.bestaetigung.kuendigen')->with('oasis.submitted.kuendigen', true);
    }
}
