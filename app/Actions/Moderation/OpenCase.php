<?php

namespace App\Actions\Moderation;

use App\Enums\Moderation\EventType;
use App\Models\ContentReport;
use App\Models\ModerationCase;
use Illuminate\Support\Facades\DB;

/** Opens a single case and system event for each newly received public content report. */
class OpenCase
{
    /** The unique source key makes repeated observer delivery idempotent. */
    public function handle(ContentReport $report): ModerationCase
    {
        return DB::transaction(function () use ($report) {
            $case = ModerationCase::firstOrCreate([
                'source_type' => $report->getMorphClass(), 'source_id' => $report->id,
            ], ['subject_url' => $report->url, 'status' => 'open', 'priority' => 'normal']);
            if ($case->wasRecentlyCreated) {
                $case->events()->create(['type' => EventType::Opened, 'data' => ['source' => 'content_report']]);
            }

            return $case;
        });
    }
}
