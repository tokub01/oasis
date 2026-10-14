<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Laravel database hints plus separate, cascading per-account preferences. */
return new class extends Migration
{
    /** Create hint storage without adding personal content to users. */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index('created_at');
            // This network only notifies User; account deletion must erase its hints too.
            $table->foreign('notifiable_id')->references('id')->on('users')->cascadeOnDelete();
        });
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('kinds')->nullable(); // Each NoticeKind maps to a boolean; missing means enabled.
            $table->boolean('digest_enabled')->default(false); // Explicit opt-in only.
            $table->timestamp('last_digest_at')->nullable(); // Successfully processed digest cutoff.
            $table->timestamps();
        });
    }

    /** Remove only this package's tables. */
    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
    }
};
