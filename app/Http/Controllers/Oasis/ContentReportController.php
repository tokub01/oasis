<?php

namespace App\Http\Controllers\Oasis;

use App\Http\Controllers\Controller;
use App\Http\Requests\Oasis\ContentReportFormRequest;
use App\Mail\Oasis\ContentReportNotice;
use App\Mail\Oasis\ContentReportReceipt;
use App\Models\ContentReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

class ContentReportController extends Controller
{
    public function store(ContentReportFormRequest $request): RedirectResponse
    {
        $submission = ContentReport::create([...$request->safe()->only(['url', 'reason', 'name', 'email']), 'truthful_confirmed_at' => now()]);

        Mail::to($submission->email)->send(new ContentReportReceipt($submission));
        Mail::to(config('oasis.mail.moderation'))->send(new ContentReportNotice($submission));

        return redirect()->route('oasis.bestaetigung.melden')->with('oasis.submitted.melden', true);
    }
}
