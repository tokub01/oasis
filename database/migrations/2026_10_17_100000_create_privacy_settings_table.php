<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Privacy settings per account (Privatsphäre-Center). Existing profiles keep their choice: a public profile stays
 * public and its new Tropfen stay public by default, exactly as before. Accounts without a profile get no row and
 * use the privacy-by-default values of App\Models\PrivacySetting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('profile_audience', 16)->default('members');
            $table->string('default_post_visibility', 16)->default('members');
            $table->string('follower_count_audience', 16)->default('members');
            $table->string('mentions_audience', 16)->default('members');
            $table->string('replies_audience', 16)->default('members');
            $table->string('carry_audience', 16)->default('members');
            $table->string('chat_invites_audience', 16)->default('members');
            $table->boolean('searchable')->default(true);
            $table->boolean('search_engines')->default(false);
            $table->timestamps();
        });

        // Carry over the profile visibility in one statement: public → everyone, members → members; the post default
        // followed it. Public profiles could be found by search engines until now; that stays until the member decides.
        DB::table('privacy_settings')->insertUsing(
            ['user_id', 'profile_audience', 'default_post_visibility', 'search_engines', 'created_at', 'updated_at'],
            DB::table('profiles')->selectRaw(
                "user_id, CASE WHEN visibility = 'public' THEN 'everyone' ELSE 'members' END,"
                ." CASE WHEN visibility = 'public' THEN 'public' ELSE 'members' END,"
                ." visibility = 'public', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP"
            ),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_settings');
    }
};
