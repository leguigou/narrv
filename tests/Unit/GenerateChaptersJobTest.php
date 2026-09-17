<?php

namespace Tests\Unit;

use App\Jobs\GenerateChapterThumbnails;
use App\Jobs\GenerateChapters;
use App\Models\Transcript;
use App\Models\Video;
use App\Services\ChapterPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class GenerateChaptersJobTest extends TestCase
{
    use RefreshDatabase;

    private function videoWithTranscript(array $attributes = []): Video
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
            'segments_json' => [
                ['start' => 0, 'end' => 10, 'text' => 'Bonjour tout le monde.'],
            ],
        ]);

        return $video;
    }

    public function test_it_stores_the_generated_chapters_and_queues_their_thumbnails(): void
    {
        Queue::fake();

        $video = $this->videoWithTranscript(['chapters_status' => 'pending']);

        $planner = Mockery::mock(ChapterPlanner::class);
        $planner->shouldReceive('plan')->once()->andReturn([
            ['title' => 'Introduction', 'start_time' => 0.0, 'end_time' => 300.0, 'duration' => 300.0],
            ['title' => 'La suite', 'start_time' => 300.0, 'end_time' => 600.0, 'duration' => 300.0],
        ]);

        (new GenerateChapters($video))->handle($planner);

        $video->refresh();
        $this->assertSame('ready', $video->chapters_status);
        $this->assertSame('ai', $video->chapters_source);
        $this->assertCount(2, $video->chapters_json);
        $this->assertSame('Introduction', $video->chapters_json[0]['title']);
        $this->assertSame('pending', $video->chapter_thumbnails_status);
        Queue::assertPushed(GenerateChapterThumbnails::class);
    }

    public function test_it_does_nothing_when_chapters_already_exist(): void
    {
        Queue::fake();

        $video = $this->videoWithTranscript([
            'chapters_status' => 'pending',
            'chapters_json' => [['title' => 'Chapitre YouTube', 'start_time' => 0.0]],
            'chapters_source' => 'youtube',
        ]);

        $planner = Mockery::mock(ChapterPlanner::class);
        $planner->shouldNotReceive('plan');

        (new GenerateChapters($video))->handle($planner);

        $video->refresh();
        $this->assertSame('ready', $video->chapters_status);
        $this->assertSame('youtube', $video->chapters_source);
        $this->assertCount(1, $video->chapters_json);
        Queue::assertNotPushed(GenerateChapterThumbnails::class);
    }

    public function test_it_marks_the_video_as_error_when_there_is_no_transcript(): void
    {
        $video = Video::create([
            'youtube_id' => 'dQw4w9WgXcQ',
            'url' => 'https://youtu.be/dQw4w9WgXcQ',
            'status' => 'ready',
            'chapters_status' => 'pending',
        ]);

        $planner = Mockery::mock(ChapterPlanner::class);
        $planner->shouldNotReceive('plan');

        (new GenerateChapters($video))->handle($planner);

        $this->assertSame('error', $video->fresh()->chapters_status);
    }

    public function test_it_marks_the_video_as_error_when_the_model_fails(): void
    {
        $video = $this->videoWithTranscript(['chapters_status' => 'pending']);

        $planner = Mockery::mock(ChapterPlanner::class);
        $planner->shouldReceive('plan')->once()->andThrow(new \RuntimeException('DeepSeek API returned HTTP 429'));

        (new GenerateChapters($video))->handle($planner);

        $video->refresh();
        $this->assertSame('error', $video->chapters_status);
        $this->assertEmpty($video->chapters_json);
    }

    public function test_it_ignores_a_video_that_is_not_pending(): void
    {
        $video = $this->videoWithTranscript(['chapters_status' => 'processing']);

        $planner = Mockery::mock(ChapterPlanner::class);
        $planner->shouldNotReceive('plan');

        (new GenerateChapters($video))->handle($planner);

        $this->assertSame('processing', $video->fresh()->chapters_status);
    }
}
