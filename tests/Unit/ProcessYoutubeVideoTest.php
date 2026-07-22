<?php

namespace Tests\Unit;

use App\Jobs\GenerateChapterThumbnails;
use App\Jobs\ProcessYoutubeVideo;
use App\Models\Video;
use App\Services\YoutubeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ProcessYoutubeVideoTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_finishes_analysis_before_queuing_chapter_thumbnails(): void
    {
        Queue::fake();

        $video = Video::create([
            'youtube_id' => 'dQw4w9WgXcQ',
            'url' => 'https://youtu.be/dQw4w9WgXcQ',
            'status' => 'pending',
        ]);

        $chapters = [[
            'title' => 'Introduction',
            'start_time' => 0.0,
            'end_time' => 60.0,
            'duration' => 60.0,
        ]];
        $metadata = [
            'title' => 'Test video',
            'duration' => 60,
            'chapters' => $chapters,
        ];

        $service = Mockery::mock(YoutubeService::class);
        $service->shouldReceive('fetchMetadata')->once()->andReturn($metadata);
        $service->shouldReceive('extractChapters')->once()->with($metadata)->andReturn($chapters);
        $service->shouldReceive('transcriptFromMetadata')->once()->andThrow(new RuntimeException('No subtitles'));

        (new ProcessYoutubeVideo($video))->handle($service);

        $video->refresh();
        $this->assertSame('ready', $video->status);
        $this->assertSame('pending', $video->chapter_thumbnails_status);
        Queue::assertPushed(
            GenerateChapterThumbnails::class,
            fn (GenerateChapterThumbnails $job) => $job->connection === null
        );
    }
}
