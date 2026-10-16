<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gift_memberships', function (Blueprint $table): void {
            $table->unique('mollie_payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('gift_memberships', function (Blueprint $table): void {
            $table->dropUnique(['mollie_payment_id']);
        });
    }
};
