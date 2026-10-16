<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 10 automated content check (docs/oasis-auto-moderation.md).
 *
 * `content_checks`: one row per checked text (Tropfen, profile, oasis) with the category scores – never the text
 * itself (it stays in its own table). Flagged rows are the source of an automated moderation case.
 * `posts.held_at`: set while a clearly sexual Tropfen waits for human review; only the author still sees it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_checks', function (Blueprint $table): void {
            $table->id();
            $table->morphs('checkable');
            $table->string('status', 16)->default('pending');
            $table->json('scores')->nullable();
            $table->string('reason', 500)->nullable();
            $table->string('source', 16)->nullable();
            $table->boolean('flagged')->default(false);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->unique(['checkable_type', 'checkable_id']);
            $table->index(['status', 'created_at']);
        });

        Schema::table('posts', function (Blueprint $table): void {
            $table->timestamp('held_at')->nullable()->after('removed_from_oasis_at');
        });
    }

    public function down(): void
    {
        Schema::table('posts', fn (Blueprint $table) => $table->dropColumn('held_at'));
        Schema::dropIfExists('content_checks');
    }
};
