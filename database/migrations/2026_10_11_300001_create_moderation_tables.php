<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** DSA case history with polymorphic sources; dependent history is purged only with its retained case. */
return new class extends Migration
{
    /** Create cases, reasoned decisions, second-person complaints and the audit trail. */
    public function up(): void
    {
        Schema::create('moderation_cases', function (Blueprint $table) {
            $table->id();
            $table->morphs('source');
            $table->unique(['source_type', 'source_id']);
            $table->text('subject_url');
            $table->foreignId('subject_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('open')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority')->default('normal')->index();
            $table->timestamps();
        });
        Schema::create('moderation_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('moderation_cases')->cascadeOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('outcome');
            $table->string('ground');
            $table->string('rule_reference');
            $table->text('facts');
            $table->text('explanation');
            $table->boolean('automated')->default(false);
            $table->timestamp('decided_at')->index();
            $table->timestamp('notified_at')->nullable();
            $table->boolean('is_complaint_review')->default(false);
            $table->foreignId('supersedes_decision_id')->nullable()->constrained('moderation_decisions')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('moderation_complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('moderation_cases')->cascadeOnDelete();
            $table->foreignId('decision_id')->constrained('moderation_decisions')->cascadeOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('submitter_email', 254);
            $table->text('reason');
            $table->string('status')->default('open')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_explanation')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['decision_id', 'submitter_email']);
        });
        Schema::create('moderation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('moderation_cases')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->json('data');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /** Drop dependent tables before their parent cases. */
    public function down(): void
    {
        Schema::dropIfExists('moderation_events');
        Schema::dropIfExists('moderation_complaints');
        Schema::dropIfExists('moderation_decisions');
        Schema::dropIfExists('moderation_cases');
    }
};
