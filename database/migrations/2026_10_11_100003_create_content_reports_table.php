<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores public DSA Art. 16 notices and the separate time of the good-faith declaration.
 * Runs through Laravel migrations alongside the corresponding Oasis model.
 */
return new class extends Migration
{
    /**
     * Create the notice schema used by ContentReport; truthful_confirmed_at is not a moderation decision.
     */
    public function up(): void
    {
        Schema::create('content_reports', function (Blueprint $table) {
            $table->id();
            $table->text('url');
            $table->text('reason');
            $table->string('name');
            $table->string('email');
            $table->timestamp('truthful_confirmed_at');
            $table->timestamps();
        });
    }

    /**
     * Remove this schema when rolling back; its retained submissions are deleted with the table.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_reports');
    }
};
