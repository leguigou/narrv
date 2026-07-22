<?php

namespace Tests\Unit;

use App\Jobs\GenerateChapterThumbnails;
use App\Jobs\ProcessYoutubeVideo;
use App\Models\Transcript;
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

    public function test_it_makes_the_video_available_before_processing_the_transcript(): void
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
        $service->shouldReceive('transcriptFromMetadata')
            ->once()
            ->withArgs(function (Video $processingVideo, array $receivedMetadata) use ($metadata): bool {
                $processingVideo->refresh();

                $this->assertSame('ready', $processingVideo->status);
                $this->assertSame('processing', $processingVideo->transcript_status);
                $this->assertSame($metadata, $receivedMetadata);
                Queue::assertPushed(GenerateChapterThumbnails::class);

                return true;
            })
            ->andThrow(new RuntimeException('No subtitles'));

        (new ProcessYoutubeVideo($video))->handle($service);

        $video->refresh();
        $this->assertSame('ready', $video->status);
        $this->assertSame('unavailable', $video->transcript_status);
        $this->assertSame('pending', $video->chapter_thumbnails_status);
        Queue::assertPushed(
            GenerateChapterThumbnails::class,
            fn (GenerateChapterThumbnails $job) => $job->connection === null
        );
    }

    public function test_it_marks_the_background_transcript_as_ready_after_storing_it(): void
    {
        Queue::fake();

        $video = Video::create([
            'youtube_id' => 'dQw4w9WgXcQ',
            'url' => 'https://youtu.be/dQw4w9WgXcQ',
            'status' => 'pending',
            'transcript_status' => 'pending',
        ]);

        $metadata = [
            'title' => 'Test video',
            'duration' => 60,
            'chapters' => [],
        ];

        $service = Mockery::mock(YoutubeService::class);
        $service->shouldReceive('fetchMetadata')->once()->andReturn($metadata);
        $service->shouldReceive('extractChapters')->once()->with($metadata)->andReturn([]);
        $service->shouldReceive('transcriptFromMetadata')->once()->andReturn([
            'raw_file_path' => 'transcripts/dQw4w9WgXcQ.fr.vtt',
            'full_text' => 'Transcript enregistré après les métadonnées.',
            'language' => 'fr',
            'word_count' => 5,
            'segments_json' => [
                ['start' => 0, 'end' => 5, 'text' => 'Transcript enregistré après les métadonnées.'],
            ],
        ]);

        (new ProcessYoutubeVideo($video))->handle($service);

        $video->refresh();
        $this->assertSame('ready', $video->status);
        $this->assertSame('ready', $video->transcript_status);
        $this->assertDatabaseHas('transcripts', [
            'video_id' => $video->id,
            'language' => 'fr',
        ]);
        $this->assertSame('Transcript enregistré après les métadonnées.', Transcript::where('video_id', $video->id)->value('full_text'));
        Queue::assertNotPushed(GenerateChapterThumbnails::class);
    }
}
