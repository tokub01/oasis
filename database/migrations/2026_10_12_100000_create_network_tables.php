<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 2 core network (docs/oasis-network.md): profiles, oases with hosts, Tropfen with images, replies and
 * "weitertragen", follows of people and oases, quiet reactions and invitation links.
 *
 * Deleting an account cascades to everything it created; replies and carries of deleted Tropfen keep their
 * reference and show "Tropfen nicht mehr verfügbar".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('bio', 300)->nullable();
            $table->string('location', 80)->nullable();
            // public: readable by guests; members: only signed-in members.
            $table->string('visibility', 16)->default('members');
            $table->string('avatar_color', 16)->default('sun');
            $table->timestamps();
        });

        Schema::create('oases', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name', 60);
            $table->string('description', 300);
            $table->text('house_rules');
            $table->string('visibility', 16)->default('public');
            $table->string('color', 16)->default('spring');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('oasis_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('oasis_id')->constrained('oases')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16)->default('member');
            $table->timestamp('joined_at');
            $table->unique(['oasis_id', 'user_id']);
            $table->index(['user_id', 'role']);
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('oasis_id')->nullable()->constrained('oases')->nullOnDelete();
            // No foreign keys: a reply or carry keeps the id of a deleted Tropfen, so it stays a reply/carry and
            // shows "nicht mehr verfügbar" instead of turning into a stand-alone Tropfen.
            $table->unsignedBigInteger('reply_to_id')->nullable();
            $table->unsignedBigInteger('carried_post_id')->nullable()->index();
            $table->text('body');
            $table->string('visibility', 16);
            // Set when a host removes the Tropfen from their oasis (house rules); the author still sees it.
            $table->timestamp('removed_from_oasis_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'id']);
            $table->index(['oasis_id', 'id']);
            $table->index(['reply_to_id', 'id']);
        });

        Schema::create('post_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('alt', 300);
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->unsignedTinyInteger('position');
        });

        Schema::create('follows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $table->morphs('followable');
            $table->timestamp('created_at');
            $table->unique(['follower_id', 'followable_type', 'followable_id']);
        });

        Schema::create('reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at');
            $table->unique(['post_id', 'user_id']);
        });

        Schema::create('invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inviter_id')->constrained('users')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['invitations', 'reactions', 'follows', 'post_images', 'posts', 'oasis_members', 'oases', 'profiles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
