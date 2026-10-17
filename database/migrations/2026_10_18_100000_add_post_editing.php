<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Editing Tropfen (docs/oasis-network.md, "Bearbeiten"): the author may change the text for a short time; every
 * earlier version is kept and visible to everyone who may read the Tropfen. Versions go with the Tropfen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->timestamp('edited_at')->nullable()->after('quarantined_at');
        });

        Schema::create('post_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            // When this text was replaced by a newer one.
            $table->timestamp('replaced_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_revisions');
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropColumn('edited_at');
        });
    }
};
