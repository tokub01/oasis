<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gift_memberships', function (Blueprint $table): void {
            $table->unique('stripe_checkout_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('gift_memberships', function (Blueprint $table): void {
            $table->dropUnique(['stripe_checkout_session_id']);
        });
    }
};
