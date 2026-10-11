<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retains public withdrawal declarations under §§ 355 ff. BGB for receipts and operator follow-up.
 * Runs through Laravel migrations alongside the corresponding Oasis model.
 */
return new class extends Migration
{
    /**
     * Create the declaration schema used by WithdrawalRequest; the original contract date is optional.
     */
    public function up(): void
    {
        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('contract');
            $table->date('contracted_on')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Remove this schema when rolling back; its retained submissions are deleted with the table.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawal_requests');
    }
};
