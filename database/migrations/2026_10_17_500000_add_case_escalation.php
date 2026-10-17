<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Escalation of moderation cases (docs/oasis-moderation.md, "Eskalation"):
 * - threat to life or safety (Art. 18 DSA) and depictions of child sexual abuse get an escalation type, the time and
 *   who escalated, and – entered by hand – when and under which reference a person reported it to an authority;
 * - evidence is secured separately from the content, so it survives deletion, and is removed after a fixed period;
 * - a quarantined Tropfen serves its images to nobody, staff included.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('moderation_cases', function (Blueprint $table): void {
            $table->string('escalation', 32)->nullable()->after('priority')->index();
            $table->timestamp('escalated_at')->nullable()->after('escalation');
            $table->foreignId('escalated_by')->nullable()->after('escalated_at')->constrained('users')->nullOnDelete();
            $table->timestamp('authority_reported_at')->nullable()->after('escalated_by');
            $table->string('authority_reference', 160)->nullable()->after('authority_reported_at');
        });

        Schema::table('posts', function (Blueprint $table): void {
            $table->timestamp('quarantined_at')->nullable()->after('held_at');
        });

        Schema::create('moderation_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->constrained('moderation_cases')->cascadeOnDelete();
            $table->string('kind', 16); // text|image
            $table->text('content')->nullable();
            $table->string('path')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->unsignedInteger('bytes')->nullable();
            $table->json('meta');
            $table->foreignId('captured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('purge_after')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_evidence');
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropColumn('quarantined_at');
        });
        Schema::table('moderation_cases', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('escalated_by');
            $table->dropColumn(['escalation', 'escalated_at', 'authority_reported_at', 'authority_reference']);
        });
    }
};
