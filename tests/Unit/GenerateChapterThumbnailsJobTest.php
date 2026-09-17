<?php

namespace Tests\Unit;

use App\Jobs\GenerateChapterThumbnails;
use App\Models\Video;
use App\Services\YoutubeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GenerateChapterThumbnailsJobTest extends TestCase
{
    use RefreshDatabase;

    private function videoWithChapters(array $attributes = []): Video
    {
        return Video::create(array_merge([
            'youtube_id' => 'dQw4w9WgXcQ',
            'url' => 'https://youtu.be/dQw4w9WgXcQ',
            'title' => 'Une video avec chapitres',
            'duration' => 600,
            'status' => 'ready',
            'transcript_status' => 'ready',
            'chapters_json' => [
                ['title' => 'Introduction', 'start_time' => 0.0, 'end_time' => 300.0, 'duration' => 300.0],
                ['title' => 'La suite', 'start_time' => 300.0, 'end_time' => 600.0, 'duration' => 300.0],
            ],
            'chapter_thumbnails_status' => 'pending',
        ], $attributes));
    }

    public function test_it_stores_each_thumbnail_as_soon_as_it_is_ready(): void
    {
        $video = $this->videoWithChapters();

        $service = Mockery::mock(YoutubeService::class);
        $service->shouldReceive('generateChapterThumbnails')
            ->once()
            ->andReturnUsing(function (Video $processingVideo, callable $onChapter) use ($video): array {
                $chapters = $video->chapters_json;
                $chapters[0]['thumbnail_url'] = "/api/videos/{$video->id}/chapters/0/thumbnail?v=1";

                // Première miniature prete : elle doit deja etre en base pour que
                // le front affiche la progression pendant la generation.
                $onChapter($chapters);
                $this->assertSame(
                    '/api/videos/' . $video->id . '/chapters/0/thumbnail?v=1',
                    Video::find($video->id)->chapters_json[0]['thumbnail_url'] ?? null
                );

                $chapters[1]['thumbnail_url'] = "/api/videos/{$video->id}/chapters/1/thumbnail?v=2";
                $onChapter($chapters);

                return $chapters;
            });

        (new GenerateChapterThumbnails($video))->handle($service);

        $video->refresh();
        $this->assertSame('ready', $video->chapter_thumbnails_status);
        $this->assertSame('/api/videos/' . $video->id . '/chapters/1/thumbnail?v=2', $video->chapters_json[1]['thumbnail_url']);
    }

    public function test_it_marks_the_video_as_error_when_no_thumbnail_could_be_built(): void
    {
        $video = $this->videoWithChapters();

        $service = Mockery::mock(YoutubeService::class);
        $service->shouldReceive('generateChapterThumbnails')
            ->once()
            ->andThrow(new \RuntimeException('Unable to download video for chapter thumbnails'));

        (new GenerateChapterThumbnails($video))->handle($service);

        $this->assertSame('error', $video->fresh()->chapter_thumbnails_status);
    }

    public function test_it_does_nothing_when_the_video_has_no_chapter(): void
    {
        $video = $this->videoWithChapters(['chapters_json' => []]);

        $service = Mockery::mock(YoutubeService::class);
        $service->shouldNotReceive('generateChapterThumbnails');

        (new GenerateChapterThumbnails($video))->handle($service);

        $this->assertNull($video->fresh()->chapter_thumbnails_status);
    }
}
