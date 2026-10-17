<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Wessen Bilder siehst du sofort?" (docs/oasis-privacy.md): images of everyone else stay covered until the member taps
 * "Bild anzeigen". Default: people the member follows, so unsolicited images of strangers are never shown unasked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('privacy_settings', function (Blueprint $table): void {
            $table->string('images_audience', 20)->default('following')->after('chat_invites_audience');
        });
    }

    public function down(): void
    {
        Schema::table('privacy_settings', function (Blueprint $table): void {
            $table->dropColumn('images_audience');
        });
    }
};
