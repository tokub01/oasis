<?php

namespace App\Console\Commands;

use App\Jobs\Moderation\CheckContent;
use App\Models\ContentCheck;
use App\Models\Post;
use Illuminate\Console\Command;

/**
 * Safety net of the automated content check (every 10 minutes): queues Tropfen of the last two days that have no check
 * yet, and checks that are still pending after 30 minutes (lost job, worker down). Idempotent.
 */
class ModerationSweepCommand extends Command
{
    protected $signature = 'oasis:moderation-sweep';

    protected $description = 'Re-queue automated content checks that never ran';

    public function handle(): int
    {
        $morph = (new Post)->getMorphClass();
        $unchecked = Post::where('created_at', '>=', now()->subDays(2))
            ->whereNotExists(fn ($query) => $query->from('content_checks')
                ->whereColumn('content_checks.checkable_id', 'posts.id')
                ->where('content_checks.checkable_type', $morph))
            ->get();
        foreach ($unchecked as $post) {
            CheckContent::for($post);
        }

        $stale = ContentCheck::where('status', 'pending')->where('updated_at', '<', now()->subMinutes(30))->get();
        foreach ($stale as $check) {
            $check->touch();
            CheckContent::dispatch($check);
        }

        $this->info("Neu eingereiht: {$unchecked->count()} ungeprüfte, {$stale->count()} hängende Prüfungen.");

        return self::SUCCESS;
    }
}
