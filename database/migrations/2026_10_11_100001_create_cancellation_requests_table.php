<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retains public cancellation declarations for § 312k BGB receipts and operator follow-up.
 * Runs through Laravel migrations alongside the corresponding Oasis model.
 */
return new class extends Migration
{
    /**
     * Create the declaration schema used by CancellationRequest; optional fields depend on cancellation choices.
     */
    public function up(): void
    {
        Schema::create('cancellation_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('username')->nullable();
            $table->string('contract');
            $table->string('type');
            $table->text('reason')->nullable();
            $table->string('timing');
            $table->date('effective_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Remove this schema when rolling back; its retained submissions are deleted with the table.
     */
    public function down(): void
    {
        Schema::dropIfExists('cancellation_requests');
    }
};
