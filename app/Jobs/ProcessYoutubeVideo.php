<?php

namespace App\Jobs;

use App\Models\Video;
use App\Services\YoutubeService;
use App\Models\Transcript;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessYoutubeVideo implements ShouldQueue
{
    use Queueable;

    protected Video $video;

    public function __construct(Video $video)
    {
        $this->video = $video;
    }

    public function handle(): void
    {
        $this->video->update(['status' => 'processing']);

        try {
            $service = new YoutubeService();
            $data = $service->fetchTranscript($this->video);

            // Met à jour la vidéo avec les métadonnées
            $this->video->update([
                'title' => $data['title'],
                'channel_name' => $data['channel_name'],
                'channel_url' => $data['channel_url'],
                'duration' => $data['duration'],
                'thumbnail_url' => $data['thumbnail_url'],
                'language' => $data['language'],
                'status' => 'ready',
            ]);

            // Crée le transcript
            Transcript::create([
                'video_id' => $this->video->id,
                'raw_file_path' => $data['raw_file_path'],
                'full_text' => $data['full_text'],
                'language' => $data['language'],
                'word_count' => $data['word_count'],
                'segments_json' => $data['segments_json'],
            ]);

        } catch (\Exception $e) {
            $this->video->update([
                'status' => 'error',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
