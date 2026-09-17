<?php

namespace Tests\Unit;

use App\Jobs\GenerateChapters;
use App\Models\Transcript;
use App\Models\Video;
use App\Services\ChapterGenerationLauncher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ChapterGenerationLauncherTest extends TestCase
{
    use RefreshDatabase;

    private function video(array $attributes = []): Video
    {
        $video = Video::create(array_merge([
            'youtube_id' => 'dQw4w9WgXcQ',
            'url' => 'https://youtu.be/dQw4w9WgXcQ',
            'title' => 'Une video sans chapitres',
            'duration' => 600,
            'status' => 'ready',
            'transcript_status' => 'ready',
        ], $attributes));

        Transcript::create([
            'video_id' => $video->id,
            'raw_file_path' => 'transcripts/dQw4w9WgXcQ.fr.vtt',
            'full_text' => 'Bonjour tout le monde.',
            'language' => 'fr',
            'word_count' => 4,
            'segments_json' => [['start' => 0, 'end' => 10, 'text' => 'Bonjour tout le monde.']],
        ]);

        return $video;
    }

    public function test_it_queues_the_job_on_a_worker_driven_queue(): void
    {
        Queue::fake();
        config(['queue.default' => 'database']);

        $launcher = new ChapterGenerationLauncher();
        $video = $this->video();

        $this->assertTrue($launcher->launch($video));
        $this->assertSame('pending', $video->fresh()->chapters_status);
        Queue::assertPushed(GenerateChapters::class);
    }

    public function test_it_does_not_restart_a_fresh_pending_generation(): void
    {
        Queue::fake();
        config(['queue.default' => 'database']);

        $video = $this->video(['chapters_status' => 'pending', 'updated_at' => now()]);

        $this->assertFalse((new ChapterGenerationLauncher())->launch($video));
        Queue::assertNotPushed(GenerateChapters::class);
    }

    public function test_it_retries_a_stale_pending_generation(): void
    {
        Queue::fake();
        config(['queue.default' => 'database']);

        $video = $this->video(['chapters_status' => 'pending']);
        // updated_at est forcé par Eloquent à la création : on le vieillit en base.
        Video::whereKey($video->id)->update(['updated_at' => now()->subMinutes(5)]);

        $this->assertTrue((new ChapterGenerationLauncher())->launch($video->fresh()));
        Queue::assertPushed(GenerateChapters::class);
    }

    public function test_it_never_regenerates_a_video_that_already_has_chapters(): void
    {
        Queue::fake();
        config(['queue.default' => 'database']);

        $video = $this->video([
            'chapters_json' => [['title' => 'Chapitre YouTube', 'start_time' => 0]],
            'chapters_source' => 'youtube',
            'chapters_status' => 'ready',
        ]);

        $this->assertFalse((new ChapterGenerationLauncher())->launch($video));
        Queue::assertNotPushed(GenerateChapters::class);
    }

    public function test_it_does_nothing_without_a_transcript(): void
    {
        Queue::fake();
        config(['queue.default' => 'database']);

        $video = Video::create([
            'youtube_id' => 'dQw4w9WgXcQ',
            'url' => 'https://youtu.be/dQw4w9WgXcQ',
            'status' => 'ready',
            'transcript_status' => 'unavailable',
        ]);

        $this->assertFalse((new ChapterGenerationLauncher())->launch($video));
        Queue::assertNotPushed(GenerateChapters::class);
    }

    public function test_it_retries_after_an_error(): void
    {
        Queue::fake();
        config(['queue.default' => 'database']);

        $video = $this->video(['chapters_status' => 'error']);

        $this->assertTrue((new ChapterGenerationLauncher())->launch($video));
        Queue::assertPushed(GenerateChapters::class);
    }
}
