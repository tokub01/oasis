<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freundschaften and the contact filter (docs/oasis-network.md, "Freundschaften"; docs/oasis-privacy.md):
 * - friendships: a request from requester to addressee; a friendship once accepted_at is set. Independent of follows.
 * - privacy_settings.friend_requests: whether others may send the member friend requests (default yes).
 * - privacy_settings.oasis_list_audience: who sees the member in oasis member lists; default "contacts" (friends and
 *   people the member follows) for the member's own protection.
 * - images_audience: the new default is "contacts" as well (friends' images show at once).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('friendships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('addressee_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->unique(['requester_id', 'addressee_id']);
            $table->index(['addressee_id', 'accepted_at']);
        });
        Schema::table('privacy_settings', function (Blueprint $table): void {
            $table->boolean('friend_requests')->default(true)->after('chat_invites_audience');
            $table->string('oasis_list_audience', 20)->default('contacts')->after('friend_requests');
            $table->string('images_audience', 20)->default('contacts')->change();
        });
    }

    public function down(): void
    {
        Schema::table('privacy_settings', function (Blueprint $table): void {
            $table->dropColumn(['friend_requests', 'oasis_list_audience']);
            $table->string('images_audience', 20)->default('following')->change();
        });
        Schema::dropIfExists('friendships');
    }
};
