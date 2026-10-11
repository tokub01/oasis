<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores founding waitlist addresses with explicit consent and independent double-opt-in confirmation.
 * Runs through Laravel migrations alongside the corresponding Oasis model.
 */
return new class extends Migration
{
    /**
     * Create the waitlist schema used by WaitlistEntry and the signed confirmation/unsubscribe flows.
     */
    public function up(): void
    {
        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('group')->nullable();
            $table->timestamp('consented_at');
            $table->string('token', 64)->unique();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Remove this schema when rolling back; its retained submissions are deleted with the table.
     */
    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
