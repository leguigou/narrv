<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->string('transcript_status')->nullable()->after('status');
        });

        DB::table('videos')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('transcripts')
                    ->whereColumn('transcripts.video_id', 'videos.id');
            })
            ->update(['transcript_status' => 'ready']);

        DB::table('videos')
            ->where('status', 'ready')
            ->whereNull('transcript_status')
            ->update(['transcript_status' => 'unavailable']);

        DB::table('videos')
            ->whereIn('status', ['pending', 'processing'])
            ->whereNull('transcript_status')
            ->update(['transcript_status' => 'pending']);
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('transcript_status');
        });
    }
};
