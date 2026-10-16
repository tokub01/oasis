<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates tables and columns for Mollie billing, founding memberships, and gift codes.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('mollie_customer_id')->nullable()->index();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->timestamp('trial_reminder_sent_at')->nullable();
            $table->timestamp('founding_member_at')->nullable();
            $table->boolean('founding_price_guarantee')->default(false);
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type')->default('default');
            $table->string('mollie_id')->nullable()->unique();
            $table->string('status');
            $table->string('rhythm')->default('monat');
            $table->unsignedInteger('amount')->default(500);
            $table->timestamp('paid_until')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('gift_memberships', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('tier');
            $table->integer('months')->default(12);
            $table->integer('amount');
            $table->string('currency', 3)->default('eur');
            $table->foreignId('purchaser_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('purchaser_email')->nullable();
            $table->string('mollie_payment_id')->nullable();
            $table->foreignId('redeemed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gift_memberships');
        Schema::dropIfExists('subscriptions');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['mollie_customer_id']);
            $table->dropColumn([
                'mollie_customer_id',
                'pm_type',
                'pm_last_four',
                'trial_reminder_sent_at',
                'founding_member_at',
                'founding_price_guarantee',
            ]);
        });
    }
};
