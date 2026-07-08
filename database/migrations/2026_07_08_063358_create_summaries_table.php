<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transcript_id')->constrained()->cascadeOnDelete();
            $table->longText('content');
            $table->string('model')->default('deepseek-chat');
            $table->float('temperature')->default(0.3);
            $table->string('tone')->default('neutral');
            $table->string('length')->default('medium');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('summaries');
    }
};
