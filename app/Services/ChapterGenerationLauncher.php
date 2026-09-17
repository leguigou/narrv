<?php

namespace App\Services;

use App\Jobs\GenerateChapters;
use App\Models\Video;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Démarre la génération des chapitres en arrière-plan pour que la requête HTTP
 * (bouton « Créer les chapitres », import d'une vidéo) réponde immédiatement.
 */
class ChapterGenerationLauncher
{
    public function launch(Video $video): bool
    {
        $video->refresh();

        if ($video->transcript === null || ! empty($video->chapters_json)) {
            return false;
        }

        $status = $video->chapters_status;
        $isStalePending = $status === 'pending' && $video->updated_at?->lt(now()->subMinutes(2));
        $isStaleProcessing = $status === 'processing' && $video->updated_at?->lt(now()->subMinutes(25));

        if ($status !== null && $status !== 'error' && ! $isStalePending && ! $isStaleProcessing) {
            // Tâche fraîche déjà en cours : on ne relance pas.
            return false;
        }

        $query = Video::whereKey($video->id);
        $status === null
            ? $query->whereNull('chapters_status')
            : $query->where('chapters_status', $status);

        $claimed = $query->update([
            'chapters_status' => 'pending',
            'updated_at' => now(),
        ]);

        if ($claimed === 0) {
            return false;
        }

        $video->chapters_status = 'pending';
        $fresh = $video->fresh();

        try {
            if (config('queue.default') === 'sync') {
                $this->runDetached($fresh);
            } else {
                // Respecte la connexion par défaut du déploiement (database, redis, …)
                // afin que le worker actif consomme réellement ce job.
                GenerateChapters::dispatch($fresh);
            }
        } catch (Throwable $e) {
            Video::whereKey($fresh->id)
                ->where('chapters_status', 'pending')
                ->update(['chapters_status' => 'error']);

            logger()->warning('Unable to start chapter generation', [
                'source' => 'deepseek',
                'video_id' => $fresh->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Sans worker de queue, le job tournerait dans la requête HTTP : on le lance
     * donc dans un process PHP détaché (setsid + nohup, sinon Symfony Process
     * tue l'enfant en fin de requête), puis le front suit le statut.
     */
    private function runDetached(Video $video): void
    {
        $binary = PHP_BINARY !== '' && ! str_contains(PHP_BINARY, 'fpm') ? PHP_BINARY : 'php';

        $command = sprintf(
            'setsid nohup %s %s chapters:generate %s > /dev/null 2>&1 &',
            escapeshellarg($binary),
            escapeshellarg(base_path('artisan')),
            escapeshellarg((string) $video->id)
        );

        $process = Process::fromShellCommandline($command, base_path());
        $process->setTimeout(30);
        $process->run();
    }
}
