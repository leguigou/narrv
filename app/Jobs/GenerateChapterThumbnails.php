<?php

namespace App\Jobs;

use App\Models\Video;
use App\Services\YoutubeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateChapterThumbnails implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 1200;

    public function __construct(protected Video $video)
    {
    }

    public function handle(YoutubeService $service): void
    {
        $this->video->refresh();

        if (empty($this->video->chapters_json)) {
            $this->video->update(['chapter_thumbnails_status' => null]);
            return;
        }

        // Un seul job peut prendre la vidéo. Une ancienne tâche restée dans une
        // autre connexion de queue devient ainsi inoffensive si elle réapparaît.
        $claimed = Video::whereKey($this->video->id)
            ->where('chapter_thumbnails_status', 'pending')
            ->update([
                'chapter_thumbnails_status' => 'processing',
                'updated_at' => now(),
            ]);

        if ($claimed === 0) {
            return;
        }

        $this->video->refresh();

        try {
            $chapters = $service->generateChapterThumbnails($this->video);

            $this->video->update([
                'chapters_json' => $chapters,
                'chapter_thumbnails_status' => 'ready',
            ]);
        } catch (Throwable $e) {
            Video::whereKey($this->video->id)
                ->where('chapter_thumbnails_status', 'processing')
                ->update(['chapter_thumbnails_status' => 'error']);

            logger()->warning('Chapter thumbnail generation failed', [
                'source' => 'youtube',
                'video_id' => $this->video->id,
                'youtube_id' => $this->video->youtube_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Video::whereKey($this->video->id)
            ->where('chapter_thumbnails_status', 'processing')
            ->update(['chapter_thumbnails_status' => 'error']);
    }
}
