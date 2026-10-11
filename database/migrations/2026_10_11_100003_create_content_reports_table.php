<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_reports', function (Blueprint $table) {
            $table->id();
            $table->text('url');
            $table->text('reason');
            $table->string('name');
            $table->string('email');
            $table->timestamp('truthful_confirmed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_reports');
    }
};
