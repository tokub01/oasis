<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Extends starter accounts for Oasis registration and Fortify's optional two-factor authentication. */
return new class extends Migration
{
    /** Preserve existing names; unknown legacy age/consent/usernames stay null rather than being invented. */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->renameColumn('name', 'display_name');
            $table->string('username', 30)->nullable()->unique();
            $table->date('birth_date')->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
        });
    }

    /** Restore the starter schema when rolling back this stage. */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'birth_date', 'terms_accepted_at', 'trial_ends_at',
                'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
            $table->renameColumn('display_name', 'name');
        });
    }
};
