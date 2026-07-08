<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->index('status', 'videos_status_index');
            $table->index('created_at', 'videos_created_at_index');
        });

        Schema::table('transcripts', function (Blueprint $table) {
            $table->unique('video_id', 'transcripts_video_id_unique');
        });

        Schema::table('translations', function (Blueprint $table) {
            $table->unique(['transcript_id', 'target_language'], 'translations_transcript_language_unique');
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->index(['transcript_id', 'created_at'], 'chat_messages_transcript_created_index');
        });

        Schema::table('admin_sessions', function (Blueprint $table) {
            $table->index('expires_at', 'admin_sessions_expires_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('admin_sessions', function (Blueprint $table) {
            $table->dropIndex('admin_sessions_expires_at_index');
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex('chat_messages_transcript_created_index');
        });

        Schema::table('translations', function (Blueprint $table) {
            $table->dropUnique('translations_transcript_language_unique');
        });

        Schema::table('transcripts', function (Blueprint $table) {
            $table->dropUnique('transcripts_video_id_unique');
        });

        Schema::table('videos', function (Blueprint $table) {
            $table->dropIndex('videos_status_index');
            $table->dropIndex('videos_created_at_index');
        });
    }
};
