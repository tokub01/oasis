<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payment records are accounting documents (§ 147 AO, § 257 HGB): they must outlive a deleted account. The account
 * link is set to null instead of cascading; the record keeps the invoice snapshot and now also the Mollie customer id,
 * so staff can still find the payment at Mollie. Records are pruned after `oasis.privacy.payment_records_years`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_payments', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
        });
        Schema::table('membership_payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('mollie_customer_id')->nullable()->after('mollie_payment_id')->index();
        });

        DB::table('membership_payments')->whereNull('mollie_customer_id')->update([
            'mollie_customer_id' => DB::raw('(SELECT users.mollie_customer_id FROM users WHERE users.id = membership_payments.user_id)'),
        ]);
    }

    public function down(): void
    {
        DB::table('membership_payments')->whereNull('user_id')->delete();
        Schema::table('membership_payments', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['mollie_customer_id']);
            $table->dropColumn('mollie_customer_id');
        });
        Schema::table('membership_payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
