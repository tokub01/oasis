<?php

namespace App\Console\Commands;

use App\Actions\Moderation\NotifyComplaint;
use App\Actions\Moderation\NotifyDecision;
use App\Models\ModerationComplaint;
use App\Models\ModerationDecision;
use Illuminate\Console\Command;

/** Operator retry for committed moderation decisions whose external mail transport failed. */
class RetryModerationNotices extends Command
{
    protected $signature = 'oasis:moderation-notify';

    protected $description = 'Sendet noch nicht protokollierte Moderationsmitteilungen erneut.';

    /** Actions skip each previously delivered recipient based on append-only delivery evidence. */
    public function handle(NotifyDecision $decisions, NotifyComplaint $complaints): int
    {
        ModerationDecision::with('case')->each(fn (ModerationDecision $decision) => $decisions->handle($decision));
        ModerationComplaint::whereIn('status', ['upheld', 'overturned'])->each(fn (ModerationComplaint $complaint) => $complaints->handle($complaint));
        $this->info('Ausstehende Moderationsmitteilungen wurden versendet.');

        return self::SUCCESS;
    }
}
