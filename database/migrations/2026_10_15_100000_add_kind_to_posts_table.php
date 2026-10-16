<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds the kind column to posts table to differentiate regular Tropfen
     * from questions to followers ("Frage an meine Karawane").
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->string('kind', 20)->default('post')->after('visibility');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropColumn('kind');
        });
    }
};
