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
        $this->video->update([
            'status' => 'processing',
            'chapter_thumbnails_status' => null,
        ]);

        try {
            // 1. Toujours récupérer les métadonnées (titre, miniature, chapitres, etc.)
            $metadata = $service->fetchMetadata($this->video->url);

            $videoData = [
                'title' => $metadata['title'] ?? null,
                'channel_name' => $metadata['channel'] ?? $metadata['uploader'] ?? null,
                'channel_url' => $metadata['channel_url'] ?? null,
                'duration' => $metadata['duration'] ?? null,
                'published_at' => $this->publishedAtFromMetadata($metadata),
                'thumbnail_url' => $metadata['thumbnail'] ?? null,
                'chapters_json' => $service->extractChapters($metadata),
                'language' => $this->normalizeLanguageCode($metadata['language'] ?? null) ?? 'en',
                'error_message' => null,
            ];

            // Les métadonnées sont disponibles, mais l'analyse reste en cours
            // jusqu'à la fin de la récupération du transcript.
            $this->video->update($videoData);

            // 2. Essayer de récupérer les sous-titres
            try {
                $transcriptData = $service->transcriptFromMetadata($this->video, $metadata);

                $this->video->update(['language' => $transcriptData['language']]);

                // Créer le transcript
                Transcript::updateOrCreate(
                    ['video_id' => $this->video->id],
                    [
                        'raw_file_path' => $transcriptData['raw_file_path'],
                        'full_text' => $transcriptData['full_text'],
                        'language' => $transcriptData['language'],
                        'word_count' => $transcriptData['word_count'],
                        'segments_json' => $transcriptData['segments_json'],
                    ]
                );

                logger()->info('Video processed with transcript', [
                    'video_id' => $this->video->id,
                    'youtube_id' => $this->video->youtube_id,
                    'title' => $videoData['title'],
                ]);
            } catch (Throwable $e) {
                // Pas de sous-titres → on sauvegarde juste les métadonnées
                logger()->warning('No subtitles available, saving metadata only', [
                    'video_id' => $this->video->id,
                    'youtube_id' => $this->video->youtube_id,
                    'error' => $e->getMessage(),
                ]);

            }

            $hasChapters = !empty($videoData['chapters_json']);
            $this->video->update([
                'status' => 'ready',
                'chapter_thumbnails_status' => $hasChapters ? 'pending' : null,
            ]);

            // Ce job séparé démarre seulement une fois l'analyse utilisable.
            // Le téléchargement vidéo et FFmpeg ne retardent donc jamais le transcript.
            if ($hasChapters) {
                try {
                    GenerateChapterThumbnails::dispatch($this->video->fresh());
                } catch (Throwable $e) {
                    $this->video->update(['chapter_thumbnails_status' => 'error']);
                    logger()->warning('Unable to queue chapter thumbnails', [
                        'source' => 'youtube',
                        'video_id' => $this->video->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (Throwable $e) {
            // Échec complet (même les métadonnées YouTube)
            logger()->error('YouTube video processing failed completely', [
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

    private function publishedAtFromMetadata(array $metadata): ?string
    {
        foreach (['timestamp', 'release_timestamp', 'modified_timestamp'] as $key) {
            if (isset($metadata[$key]) && is_numeric($metadata[$key])) {
                return gmdate(\DATE_ATOM, (int) $metadata[$key]);
            }
        }

        foreach (['upload_date', 'release_date', 'modified_date'] as $key) {
            $value = $metadata[$key] ?? null;
            if (!is_string($value) || !preg_match('/^\d{8}$/', $value)) {
                continue;
            }

            $date = \DateTimeImmutable::createFromFormat('!Ymd', $value, new \DateTimeZone('UTC'));
            if ($date !== false) {
                return $date->format(\DATE_ATOM);
            }
        }

        return null;
    }

    private function normalizeLanguageCode(mixed $language): ?string
    {
        if (!is_string($language) || trim($language) === '') {
            return null;
        }

        $language = strtolower(str_replace('_', '-', trim($language)));
        $language = explode(',', $language)[0];

        if (!preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/', $language)) {
            return null;
        }

        return explode('-', $language)[0];
    }
}
