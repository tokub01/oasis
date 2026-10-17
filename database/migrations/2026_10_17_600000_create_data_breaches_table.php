<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Register of personal data breaches (Art. 33 Abs. 5 DSGVO): every breach is documented with facts, effects and
 * measures, also when it is not reported. The 72-hour deadline runs from `discovered_at`. Reports to the authority and
 * notices to the people affected are made by a person and recorded here; Oasis sends nothing itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_breaches', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 160);
            $table->timestamp('discovered_at');
            $table->text('description');
            $table->text('data_categories');
            $table->unsignedInteger('affected_people')->nullable();
            $table->string('risk', 8); // none|risk|high
            $table->text('consequences');
            $table->text('measures');
            $table->timestamp('authority_reported_at')->nullable();
            $table->string('authority_reference', 160)->nullable();
            $table->timestamp('people_notified_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->json('log');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_breaches');
    }
};
