<?php

namespace App\Actions\Moderation;

use App\Enums\Moderation\EventType;
use App\Mail\Moderation\DecisionNotice;
use App\Models\ContentReport;
use App\Models\ModerationDecision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/** Sends recipient-specific decision notices after commit and records successful deliveries for retries. */
class NotifyDecision
{
    /** Delivered recipients are skipped; failed transport leaves the decision available for operator retry. */
    public function handle(ModerationDecision $decision): void
    {
        $decision->load(['case.source', 'case.subjectUser', 'case.latestDecision']);
        // A retry must not send an obsolete restriction after a newer decision has replaced it.
        if ($decision->case->latestDecision->id !== $decision->id) {
            return;
        }
        $recipients = [
            'reporter' => $decision->case->source instanceof ContentReport ? $decision->case->source->email : null,
            'subject' => $decision->case->subjectUser?->email,
        ];
        foreach ($recipients as $kind => $email) {
            if (! $email || $decision->case->events()->where('type', EventType::Notified)
                ->where('data->decision_id', $decision->id)->where('data->recipient', $kind)->exists()) {
                continue;
            }
            Mail::to($email)->send(new DecisionNotice($decision, $kind));
            DB::transaction(function () use ($decision, $kind) {
                $decision->update(['notified_at' => now()]);
                $decision->case->events()->create(['type' => EventType::Notified,
                    'data' => ['decision_id' => $decision->id, 'recipient' => $kind]]);
            });
        }
    }
}
