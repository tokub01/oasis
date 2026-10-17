<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kontosperren und Verstöße (docs/oasis-moderation.md, "Sperren"):
 * - account_suspensions: a lock of the whole account. `provisional` starts at once when the automated check holds a
 *   Tropfen, `suspension` comes from a moderation decision, `ban` after the third confirmed violation. Active while
 *   ended_at is null; only staff end it. The member may write a statement (apology, justification).
 * - account_strikes: one confirmed violation per moderation case; `overturned_at` once a complaint succeeds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_suspensions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('moderation_case_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            // What the member is accused of, as shown to them: category, time, the model's reason.
            $table->text('accusation');
            // Copy of the text in question (the Tropfen may be deleted meanwhile); deleted with the account.
            $table->text('excerpt')->nullable();
            $table->text('statement')->nullable();
            $table->timestamp('statement_at')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('result', 20)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'ended_at']);
        });

        Schema::create('account_strikes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('moderation_case_id')->constrained()->cascadeOnDelete();
            $table->timestamp('overturned_at')->nullable();
            $table->timestamps();
            $table->unique('moderation_case_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_strikes');
        Schema::dropIfExists('account_suspensions');
    }
};
