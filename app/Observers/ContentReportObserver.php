<?php

namespace App\Observers;

use App\Actions\Moderation\OpenCase;
use App\Models\ContentReport;

/** Every new Art. 16 report opens a moderation case, including reports created outside the HTTP controller. */
class ContentReportObserver
{
    /** Delegate opening and its system audit event to the transactional case action. */
    public function created(ContentReport $report): void
    {
        app(OpenCase::class)->handle($report);
    }
}
