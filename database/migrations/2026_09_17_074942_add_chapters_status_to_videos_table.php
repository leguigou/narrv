<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            // null = jamais tenté, pending / processing / ready / error
            $table->string('chapters_status')->nullable()->after('chapters_json');
            // youtube = fournis par la chaîne, ai = proposés par l'agent
            $table->string('chapters_source')->nullable()->after('chapters_status');
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn(['chapters_status', 'chapters_source']);
        });
    }
};
