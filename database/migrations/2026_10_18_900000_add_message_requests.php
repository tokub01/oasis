<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nachrichtenanfragen (docs/oasis-chat.md): a chat from someone the member neither follows nor is friends with stays a
 * request (accepted_at null) until the member accepts or replies – no count, no e-mail, no push meanwhile. Existing
 * memberships count as accepted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_members', function (Blueprint $table): void {
            $table->timestamp('accepted_at')->nullable()->after('joined_at');
        });
        DB::table('conversation_members')->update(['accepted_at' => DB::raw('joined_at')]);
    }

    public function down(): void
    {
        Schema::table('conversation_members', function (Blueprint $table): void {
            $table->dropColumn('accepted_at');
        });
    }
};
