<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Profile audiences and post defaults now live independently in privacy_settings. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->dropColumn('visibility');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->string('visibility', 16)->default('members');
        });
        DB::table('profiles')->whereIn('user_id', DB::table('privacy_settings')
            ->where('profile_audience', 'everyone')->select('user_id'))->update(['visibility' => 'public']);
    }
};
