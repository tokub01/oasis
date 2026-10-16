<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Stores authoritative local orders and API-verified payments for fulfillment and refunds. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mollie_payment_id')->nullable()->unique();
            $table->string('kind');
            $table->string('tier');
            $table->string('rhythm');
            $table->unsignedInteger('amount');
            $table->string('status');
            $table->json('invoice_data')->nullable();
            $table->boolean('refunded')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_payments');
    }
};
