<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Self-service recovery when phone and recovery codes are lost (docs/oasis-accounts.md, "Zwei-Faktor verloren"):
 * password + e-mail link + a waiting period with notice before the second factor is switched off. Only a hash of the
 * link token is stored. Pruned 30 days after the last change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('two_factor_resets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('link_expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('two_factor_resets');
    }
};
