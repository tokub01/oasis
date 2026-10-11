<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 6 (docs/oasis-admin.md): monthly transparency reports and member votes.
 *
 * Votes are secret: `vote_participations` only records THAT a member voted (to allow one vote each), while the
 * choice is only added to `vote_options.votes_count`. No table links a member to an option.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transparency_reports', function (Blueprint $table): void {
            $table->id();
            // First day of the month the figures belong to; one report per month (re-publishing corrects it).
            $table->date('month')->unique();
            $table->unsignedInteger('members');
            $table->unsignedInteger('sponsored_seats');
            $table->decimal('income', 10, 2);
            // Cost items as label => euro amount, in display order.
            $table->json('cost_items');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('votes', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 160);
            $table->text('description');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('vote_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vote_id')->constrained()->cascadeOnDelete();
            $table->string('label', 120);
            $table->unsignedTinyInteger('position');
            $table->unsignedInteger('votes_count')->default(0);
        });

        Schema::create('vote_participations', function (Blueprint $table): void {
            $table->foreignId('vote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Day only, so the order of participation cannot be matched to tally changes.
            $table->date('voted_on');
            $table->primary(['vote_id', 'user_id']);
        });
    }

    public function down(): void
    {
        foreach (['vote_participations', 'vote_options', 'votes', 'transparency_reports'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
