<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('videos') || Schema::hasColumn('videos', 'published_at')) {
            return;
        }

        Schema::table('videos', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('duration')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('videos') || ! Schema::hasColumn('videos', 'published_at')) {
            return;
        }

        Schema::table('videos', function (Blueprint $table) {
            $table->dropIndex(['published_at']);
            $table->dropColumn('published_at');
        });
    }
};
