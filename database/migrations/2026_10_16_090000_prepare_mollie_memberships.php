<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Bridge for databases migrated before the provider change. Refuses to erase existing billing records. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'stripe_id')) {
            return;
        }
        if (DB::table('subscriptions')->exists() || DB::table('users')->whereNotNull('stripe_id')->exists()
            || DB::table('gift_memberships')->whereNotNull('stripe_checkout_session_id')->exists()) {
            throw new RuntimeException('Vorhandene Zahlungsdaten müssen vor dem Mollie-Umzug gesichert und individuell migriert werden. Es wurden keine Zahlungsdaten gelöscht.');
        }
        Schema::table('users', function (Blueprint $table): void {
            $table->renameColumn('stripe_id', 'mollie_customer_id');
        });
        Schema::table('gift_memberships', function (Blueprint $table): void {
            $table->renameColumn('stripe_checkout_session_id', 'mollie_payment_id');
        });
        Schema::dropIfExists('subscription_items');
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->renameColumn('stripe_id', 'mollie_id');
            $table->renameColumn('stripe_status', 'status');
            $table->dropColumn(['stripe_price', 'quantity', 'trial_ends_at']);
        });
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->string('mollie_id')->nullable()->change();
            $table->string('rhythm')->default('monat');
            $table->unsignedInteger('amount')->default(500);
            $table->timestamp('paid_until')->nullable();
        });
    }

    public function down(): void
    {
        // Provider changes cannot recreate customer mandates; rolling back code requires restoring the backup.
    }
};
