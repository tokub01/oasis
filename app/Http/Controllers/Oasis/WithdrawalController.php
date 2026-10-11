<?php

namespace App\Http\Controllers\Oasis;

use App\Http\Controllers\Controller;
use App\Http\Requests\Oasis\WithdrawalFormRequest;
use App\Mail\Oasis\WithdrawalReceipt;
use App\Models\WithdrawalRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

class WithdrawalController extends Controller
{
    public function store(WithdrawalFormRequest $request): RedirectResponse
    {
        $submission = WithdrawalRequest::create([...$request->safe()->only(['name', 'email', 'contract', 'contracted_on'])]);

        Mail::to($submission->email)->send(new WithdrawalReceipt($submission));
        Mail::to(config('oasis.mail.operator'))->send(new WithdrawalReceipt($submission, operatorCopy: true));

        return redirect()->route('oasis.bestaetigung.widerrufen')->with('oasis.submitted.widerrufen', [
            ...$submission->only(['name', 'email', 'contract']),
            'received_at' => $submission->created_at->timezone('Europe/Berlin')->format('d.m.Y H:i:s T'),
            'contracted_on' => $submission->contracted_on?->format('d.m.Y'),
        ]);
    }
}
