<?php

namespace App\Jobs;

use App\Models\Transcript;
use App\Models\Video;
use App\Services\YoutubeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessYoutubeVideo implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 240;

    protected Video $video;

    public function __construct(Video $video)
    {
        $this->video = $video;
    }

    public function handle(YoutubeService $service): void
    {
        $this->video->update(['status' => 'processing']);

        try {
            $data = $service->fetchTranscript($this->video);

            $this->video->update([
                'title' => $data['title'],
                'channel_name' => $data['channel_name'],
                'channel_url' => $data['channel_url'],
                'duration' => $data['duration'],
                'published_at' => $data['published_at'],
                'thumbnail_url' => $data['thumbnail_url'],
                'language' => $data['language'],
                'chapters_json' => $data['chapters'] ?? [],
                'status' => 'ready',
                'error_message' => null,
            ]);

            Transcript::updateOrCreate(
                ['video_id' => $this->video->id],
                [
                    'raw_file_path' => $data['raw_file_path'],
                    'full_text' => $data['full_text'],
                    'language' => $data['language'],
                    'word_count' => $data['word_count'],
                    'segments_json' => $data['segments_json'],
                ]
            );
        } catch (Throwable $e) {
            logger()->error('YouTube video processing failed', [
                'source' => 'youtube',
                'video_id' => $this->video->id,
                'youtube_id' => $this->video->youtube_id,
                'url' => $this->video->url,
                'error' => $e->getMessage(),
            ]);

            $this->video->update([
                'status' => 'error',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
