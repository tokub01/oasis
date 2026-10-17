<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Push-Hinweise (docs/oasis-pwa.md): one row per browser that switched pushes on. The endpoint is the browser
 * vendor's push address for this browser; p256dh and auth are the browser's public key and secret, used to encrypt
 * every message so the push service cannot read it. Deleted with the account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            // sha256 of the endpoint: endpoints can be longer than an index allows.
            $table->char('endpoint_hash', 64)->unique();
            $table->string('p256dh', 100);
            $table->string('auth', 40);
            $table->string('device', 80);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
