<?php

namespace App\Http\Controllers\Oasis;

use App\Http\Controllers\Controller;
use App\Http\Requests\Oasis\CancellationFormRequest;
use App\Mail\Oasis\CancellationReceipt;
use App\Models\CancellationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

/**
 * Receives public membership cancellations through oasis.kuendigen.store.
 * Under § 312k BGB, the declaration must be retainable and promptly confirmed in text form.
 */
class CancellationController extends Controller
{
    /**
     * Persist the validated declaration, send both receipts, and flash a retainable copy.
     * Mail failures propagate before success is flashed; the stored declaration remains for follow-up.
     */
    public function store(CancellationFormRequest $request): RedirectResponse
    {
        $submission = CancellationRequest::create($request->safe()->only([
            'name', 'email', 'username', 'contract', 'type', 'reason', 'timing', 'effective_date',
        ]));

        Mail::to($submission->email)->send(new CancellationReceipt($submission));
        Mail::to(config('oasis.mail.operator'))->send(new CancellationReceipt($submission, operatorCopy: true));

        return redirect()->route('oasis.bestaetigung.kuendigen')->with('oasis.submitted.kuendigen', [
            ...$submission->only(['name', 'email', 'username', 'contract', 'type', 'reason', 'timing']),
            'received_at' => $submission->created_at->timezone('Europe/Berlin')->format('d.m.Y H:i:s T'),
            'effective_date' => $submission->effective_date?->format('d.m.Y'),
        ]);
    }
}
