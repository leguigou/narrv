<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('youtube_id')->unique();
            $table->string('url');
            $table->string('title')->nullable();
            $table->string('channel_name')->nullable();
            $table->string('channel_url')->nullable();
            $table->integer('duration')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->string('language')->default('en');
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->json('formats_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
