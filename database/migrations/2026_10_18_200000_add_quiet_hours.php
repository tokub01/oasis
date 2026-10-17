<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ruhezeiten and Pause (docs/oasis-notifications.md): a daily quiet window in German time and a pause until a moment.
 * During either, Oasis sends no hint mails or pushes; hints still collect in the app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table): void {
            $table->boolean('quiet_enabled')->default(false)->after('digest_enabled');
            $table->time('quiet_from')->default('22:00:00')->after('quiet_enabled');
            $table->time('quiet_until')->default('07:00:00')->after('quiet_from');
            $table->timestamp('paused_until')->nullable()->after('quiet_until');
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table): void {
            $table->dropColumn(['quiet_enabled', 'quiet_from', 'quiet_until', 'paused_until']);
        });
    }
};
