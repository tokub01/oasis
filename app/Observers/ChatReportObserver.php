<?php

namespace App\Observers;

use App\Actions\Moderation\OpenCase;
use App\Models\ChatReport;

/**
 * Every participant chat report opens a moderation case, including reports created outside the HTTP controller.
 * Used by ModerationServiceProvider and OpenCase.
 */
class ChatReportObserver
{
    /** Delegate opening and its system audit event to the transactional case action. */
    public function created(ChatReport $report): void
    {
        app(OpenCase::class)->handle($report);
    }
}
