<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * More privacy controls (Privatsphäre-Center, docs/oasis-privacy.md):
 * - who sees the location and the oasis memberships on the profile, login alerts and hidden words;
 * - restrictions ("Einschränken"): replies of a restricted person under the member's Tropfen are seen only by that
 *   person, and the member gets no hints from them;
 * - known devices for login alerts: only a browser/system label, no IP address, pruned after a year without login.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('privacy_settings', function (Blueprint $table): void {
            $table->string('location_audience', 16)->default('members')->after('follower_count_audience');
            $table->string('oases_audience', 16)->default('members')->after('location_audience');
            $table->boolean('login_alerts')->default(true)->after('search_engines');
            $table->json('hidden_words')->nullable()->after('login_alerts');
        });

        Schema::create('restrictions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('restricted_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'restricted_user_id']);
            $table->index('restricted_user_id');
        });

        Schema::create('known_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 80);
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['user_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('known_devices');
        Schema::dropIfExists('restrictions');
        Schema::table('privacy_settings', function (Blueprint $table): void {
            $table->dropColumn(['location_audience', 'oases_audience', 'login_alerts', 'hidden_words']);
        });
    }
};
