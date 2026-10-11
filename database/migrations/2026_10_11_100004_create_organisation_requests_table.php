<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores organisation enquiries for operator planning without creating membership contracts.
 * Runs through Laravel migrations alongside the corresponding Oasis model.
 */
return new class extends Migration
{
    /**
     * Create the enquiry schema used by OrganisationRequest with an estimated people count.
     */
    public function up(): void
    {
        Schema::create('organisation_requests', function (Blueprint $table) {
            $table->id();
            $table->string('organisation');
            $table->unsignedInteger('people');
            $table->string('email');
            $table->timestamps();
        });
    }

    /**
     * Remove this schema when rolling back; its retained submissions are deleted with the table.
     */
    public function down(): void
    {
        Schema::dropIfExists('organisation_requests');
    }
};
