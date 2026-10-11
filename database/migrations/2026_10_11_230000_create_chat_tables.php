<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Ciphertext-only storage for the stage 3 chat; account-owned rows cascade on deletion. */
return new class extends Migration
{
    /** Create identities, membership, signed encrypted grants and expiring encrypted messages. */
    public function up(): void
    {
        Schema::create('chat_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('locked_identity');
            $table->json('public_keys');
            $table->timestamp('key_changed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10);
            $table->string('title', 100)->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('lifetime_days')->default(7);
            $table->unsignedInteger('key_version')->default(1);
            $table->boolean('rotation_required')->default(true);
            $table->timestamps();
        });
        Schema::create('conversation_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 10);
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->unique(['conversation_id', 'user_id']);
            $table->index(['user_id', 'left_at']);
        });
        Schema::create('conversation_key_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('key_version');
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('granter_id')->constrained('users')->cascadeOnDelete();
            $table->json('grant');
            $table->unique(['conversation_id', 'key_version', 'recipient_id'], 'chat_grant_recipient_version');
        });
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('key_version');
            $table->json('envelope');
            $table->timestamp('expires_at')->index();
            $table->timestamp('created_at');
            $table->index(['conversation_id', 'id']);
        });
        Schema::create('chat_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('envelope');
            $table->text('plaintext');
            $table->text('reason');
            $table->string('verification_note')->default('reported by participant, not verifiable by the server');
            $table->timestamp('message_expires_at')->nullable();
            $table->timestamps();
        });
    }

    /** Remove chat tables in foreign-key dependency order. */
    public function down(): void
    {
        Schema::dropIfExists('chat_reports');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_key_grants');
        Schema::dropIfExists('conversation_members');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('chat_identities');
    }
};
