<?php

namespace App\Jobs;

use App\Models\Video;
use App\Services\ChapterPlanner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateChapters implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(protected Video $video)
    {
    }

    public function handle(ChapterPlanner $planner): void
    {
        $this->video->refresh();

        $transcript = $this->video->transcript;

        if ($transcript === null) {
            $this->markAsError('Chapter generation skipped: no transcript');

            return;
        }

        // Des chapitres existent déjà (YouTube ou génération précédente) : rien à faire.
        if (! empty($this->video->chapters_json)) {
            Video::whereKey($this->video->id)->update([
                'chapters_status' => 'ready',
                'updated_at' => now(),
            ]);

            return;
        }

        // Un seul job prend la vidéo : une ancienne tâche restée dans une autre
        // connexion de queue devient inoffensive.
        $claimed = Video::whereKey($this->video->id)
            ->where('chapters_status', 'pending')
            ->update([
                'chapters_status' => 'processing',
                'updated_at' => now(),
            ]);

        if ($claimed === 0) {
            return;
        }

        $this->video->refresh();

        try {
            $segments = is_array($transcript->segments_json) ? $transcript->segments_json : [];
            $chapters = $planner->plan($segments, (float) ($this->video->duration ?? 0));

            $this->video->update([
                'chapters_json' => $chapters,
                'chapters_source' => 'ai',
                'chapters_status' => 'ready',
                'chapter_thumbnails_status' => 'pending',
            ]);

            $this->queueThumbnails();

            logger()->info('Chapters generated from transcript', [
                'source' => 'deepseek',
                'video_id' => $this->video->id,
                'youtube_id' => $this->video->youtube_id,
                'chapters' => count($chapters),
            ]);
        } catch (Throwable $e) {
            $this->markAsError($e->getMessage());
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->markAsError($exception?->getMessage());
    }

    private function queueThumbnails(): void
    {
        try {
            // Même pipeline que pour les chapitres YouTube : frame extraite au
            // début de chaque partie.
            GenerateChapterThumbnails::dispatch($this->video->fresh());
        } catch (Throwable $e) {
            Video::whereKey($this->video->id)->update(['chapter_thumbnails_status' => 'error']);

            logger()->warning('Unable to queue chapter thumbnails', [
                'source' => 'deepseek',
                'video_id' => $this->video->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function markAsError(?string $message): void
    {
        Video::whereKey($this->video->id)
            ->whereIn('chapters_status', ['pending', 'processing'])
            ->update(['chapters_status' => 'error', 'updated_at' => now()]);

        logger()->warning('Chapter generation failed', [
            'source' => 'deepseek',
            'video_id' => $this->video->id,
            'youtube_id' => $this->video->youtube_id,
            'error' => $message,
        ]);
    }
}
