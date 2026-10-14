<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Safety features: bidirectional blocks, unidirectional mutes, and private bookmarks.
 *
 * Rules:
 * - Deleting an account cascades to all blocks, mutes and bookmarks involving it.
 * - Deleting a post cascades to all bookmarks on it.
 * - Unique constraints prevent duplicate pairs.
 */
return new class extends Migration
{
    /**
     * Create the safety tables (blocks, mutes, bookmarks).
     */
    public function up(): void
    {
        Schema::create('blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('blocked_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'blocked_user_id']);
            $table->index(['blocked_user_id', 'user_id']);
        });

        Schema::create('mutes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('muted_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'muted_user_id']);
            $table->index('muted_user_id');
        });

        Schema::create('bookmarks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'post_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Drop the safety tables (bookmarks, mutes, blocks).
     */
    public function down(): void
    {
        Schema::dropIfExists('bookmarks');
        Schema::dropIfExists('mutes');
        Schema::dropIfExists('blocks');
    }
};
