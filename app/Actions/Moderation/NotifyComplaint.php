<?php

namespace App\Actions\Moderation;

use App\Enums\Moderation\EventType;
use App\Mail\Moderation\ComplaintResult;
use App\Models\ModerationComplaint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/** Delivers the reasoned second-person result without disclosing reporter contacts to other parties. */
class NotifyComplaint
{
    /** Save delivery evidence only after successful mail transport; retries skip recorded delivery. */
    public function handle(ModerationComplaint $complaint): void
    {
        if ($complaint->case->events()->where('type', EventType::Notified)
            ->where('data->complaint_id', $complaint->id)->exists()) {
            return;
        }
        Mail::to($complaint->submitter_email)->send(new ComplaintResult($complaint));
        DB::transaction(fn () => $complaint->case->events()->create(['type' => EventType::Notified,
            'data' => ['complaint_id' => $complaint->id, 'recipient' => 'complainant']]));
    }
}
