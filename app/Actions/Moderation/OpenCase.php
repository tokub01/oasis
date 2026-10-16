<?php

namespace App\Actions\Moderation;

use App\Enums\Moderation\EventType;
use App\Models\ChatReport;
use App\Models\ContentCheck;
use App\Models\ContentReport;
use App\Models\ModerationCase;
use Illuminate\Support\Facades\DB;

/** Opens a single case and system event per public content report, participant chat report or automated detection. */
class OpenCase
{
    /** The unique source key makes repeated observer delivery idempotent. */
    public function handle(ContentReport|ChatReport|ContentCheck $report): ModerationCase
    {
        return DB::transaction(function () use ($report) {
            $attributes = [
                'status' => 'open',
                'priority' => 'normal',
            ];

            if ($report instanceof ContentReport) {
                $attributes['subject_url'] = $report->url;
            } elseif ($report instanceof ChatReport) {
                // Reference only: staff are not members and cannot open the chat; they see just the reported message.
                $attributes['subject_url'] = route('app.chat').'#c/'.$report->conversation_id;
                $attributes['subject_user_id'] = $report->sender_id;
            } elseif ($report instanceof ContentCheck) {
                // Automated detection (stage 10): sexual content or threats go to the top of the queue.
                $attributes['subject_url'] = $report->subjectUrl();
                $attributes['subject_user_id'] = $report->subjectUserId();
                $attributes['priority'] = $report->verdict()->isUrgent() ? 'urgent' : 'normal';
            }

            $case = ModerationCase::firstOrCreate([
                'source_type' => $report->getMorphClass(),
                'source_id' => $report->id,
            ], $attributes);

            if ($case->wasRecentlyCreated) {
                $case->events()->create(['type' => EventType::Opened, 'data' => ['source' => $report->getMorphClass()]]);
            }

            return $case;
        });
    }
}
