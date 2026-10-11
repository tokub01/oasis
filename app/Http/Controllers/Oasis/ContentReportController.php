<?php

namespace App\Http\Controllers\Oasis;

use App\Http\Controllers\Controller;
use App\Http\Requests\Oasis\ContentReportFormRequest;
use App\Mail\Oasis\ContentReportNotice;
use App\Mail\Oasis\ContentReportReceipt;
use App\Models\ContentReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

/**
 * Receives public notices of allegedly illegal content through oasis.melden.store.
 * DSA Art. 16 requires contact information, a reason and a good-faith declaration.
 */
class ContentReportController extends Controller
{
    /**
     * Record the notice and declaration time, acknowledge the reporter, and notify moderation.
     * Storage acknowledges receipt only; the notice still needs a moderation decision.
     */
    public function store(ContentReportFormRequest $request): RedirectResponse
    {
        $submission = ContentReport::create([
            ...$request->safe()->only(['url', 'reason', 'name', 'email']),
            'truthful_confirmed_at' => now(),
        ]);

        Mail::to($submission->email)->send(new ContentReportReceipt($submission));
        Mail::to(config('oasis.mail.moderation'))->send(new ContentReportNotice($submission));

        return redirect()->route('oasis.bestaetigung.melden')->with('oasis.submitted.melden', true);
    }
}
