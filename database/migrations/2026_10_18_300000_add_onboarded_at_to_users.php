<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Einstieg für neue Mitglieder (docs/oasis-network.md, "Einstieg"): new accounts are led to /app/willkommen once,
 * until they finish or skip it. Existing accounts know Oasis already and count as onboarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('onboarded_at')->nullable()->after('trial_ends_at');
        });
        DB::table('users')->update(['onboarded_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('onboarded_at');
        });
    }
};
