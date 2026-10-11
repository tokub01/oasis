<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Staff roles default to membership; role elevation is never part of account mass assignment. */
return new class extends Migration
{
    /** Add the indexed staff authorization role. */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('member')->index();
        });
    }

    /** Remove role storage when rolling back this stage. */
    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
    }
};
