<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Members under 18 get the stricter limits (docs/oasis-privacy.md, "Unter 18") also for what they chose before: no
 * public profile, Karawane size, location or oasis list, no search engines, chats only from people they follow, and their public
 * Tropfen become members-only. The app enforces the same limits from now on (PrivacySetting::limitForMinor()).
 */
return new class extends Migration
{
    public function up(): void
    {
        $minors = DB::table('users')->whereNotNull('birth_date')->where('birth_date', '>', now()->subYears(18)->toDateString())->select('id');

        DB::table('privacy_settings')->whereIn('user_id', $minors)->where('profile_audience', 'everyone')->update(['profile_audience' => 'members']);
        DB::table('privacy_settings')->whereIn('user_id', $minors)->where('follower_count_audience', 'everyone')->update(['follower_count_audience' => 'members']);
        DB::table('privacy_settings')->whereIn('user_id', $minors)->where('location_audience', 'everyone')->update(['location_audience' => 'members']);
        DB::table('privacy_settings')->whereIn('user_id', $minors)->where('oases_audience', 'everyone')->update(['oases_audience' => 'members']);
        DB::table('privacy_settings')->whereIn('user_id', $minors)->where('chat_invites_audience', 'members')->update(['chat_invites_audience' => 'following']);
        DB::table('privacy_settings')->whereIn('user_id', $minors)->update(['default_post_visibility' => 'members', 'search_engines' => false]);
        DB::table('posts')->whereIn('user_id', $minors)->where('visibility', 'public')->update(['visibility' => 'members']);
    }

    public function down(): void
    {
        // Loosening the privacy of minors again is not a migration's decision.
    }
};
