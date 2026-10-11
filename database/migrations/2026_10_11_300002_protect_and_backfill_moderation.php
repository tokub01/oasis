<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Bring existing public notices into the moderation queue. */
return new class extends Migration
{
    /** Preserve the original receipt time when backfilling existing notices. */
    public function up(): void
    {
        DB::table('content_reports')->orderBy('id')->chunkById(100, function ($reports) {
            foreach ($reports as $report) {
                DB::transaction(function () use ($report) {
                    $id = DB::table('moderation_cases')->insertGetId([
                        'source_type' => 'content_report', 'source_id' => $report->id,
                        'subject_url' => $report->url, 'status' => 'open', 'priority' => 'normal',
                        'created_at' => $report->created_at, 'updated_at' => $report->updated_at,
                    ]);
                    DB::table('moderation_events')->insert(['case_id' => $id, 'type' => 'opened',
                        'data' => json_encode(['source' => 'content_report', 'backfilled' => true]), 'created_at' => now()]);
                });
            }
        });
    }

    /** Case tables and backfilled cases are removed by the preceding table migration rollback. */
    public function down(): void {}
};
