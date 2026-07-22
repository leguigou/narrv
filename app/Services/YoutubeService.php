<?php

namespace App\Services;

use App\Models\Video;
use RuntimeException;
use Symfony\Component\Process\Process;

class YoutubeService
{
    private string $ytDlpPath;
    private string $storagePath;
    private ?string $cookiesPath;
    private float $sleepRequests;
    private float $sleepSubtitles;
    private int $retries;
    private string $retrySleep;
    private string $jsRuntimes;

    public function __construct()
    {
        $this->ytDlpPath = (string) config('services.youtube.yt_dlp_path', 'yt-dlp');
        $this->storagePath = storage_path('app/transcripts');
        $cookiesPath = config('services.youtube.cookies_path');
        $this->cookiesPath = is_string($cookiesPath) && trim($cookiesPath) !== ''
            ? $this->resolvePath($cookiesPath)
            : null;
        $this->sleepRequests = max(0, (float) config('services.youtube.sleep_requests', 1));
        $this->sleepSubtitles = max(0, (float) config('services.youtube.sleep_subtitles', 3));
        $this->retries = max(0, (int) config('services.youtube.retries', 5));
        $retrySleep = config('services.youtube.retry_sleep', 'http:exp=1:20');
        $this->retrySleep = is_string($retrySleep) ? trim($retrySleep) : '';
        $jsRuntimes = config('services.youtube.js_runtimes', 'node');
        $this->jsRuntimes = is_string($jsRuntimes) ? trim($jsRuntimes) : '';

        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    public function fetchTranscript(Video $video): array
    {
        $metadata = $this->fetchMetadata($video->url);

        return $this->transcriptFromMetadata($video, $metadata);
    }

    public function transcriptFromMetadata(Video $video, array $metadata): array
    {
        $videoLanguage = $this->normalizeLanguageCode($metadata['language'] ?? null) ?? 'en';
        $preferredLanguage = $this->normalizeLanguageCode($video->language ?? null);
        $languageCandidates = $this->subtitleLanguageCandidates($metadata, $preferredLanguage, $videoLanguage);

        $transcriptLanguage = $this->downloadSubtitles($video->url, $video->youtube_id, $languageCandidates);

        $vttFile = $this->findSubtitleFile($video->youtube_id, $transcriptLanguage);

        if ($vttFile === null) {
            throw new RuntimeException('No usable subtitles found for this YouTube video.');
        }

        $rawVtt = file_get_contents($vttFile);
        $segments = $this->parseVtt($rawVtt ?: '');
        $fullText = trim(collect($segments)->pluck('text')->implode(' '));

        if ($fullText === '') {
            throw new RuntimeException('The subtitle file is empty or could not be parsed.');
        }

        return [
            'title' => $metadata['title'] ?? null,
            'channel_name' => $metadata['channel'] ?? $metadata['uploader'] ?? null,
            'channel_url' => $metadata['channel_url'] ?? null,
            'duration' => $metadata['duration'] ?? null,
            'published_at' => $this->publishedAtFromMetadata($metadata),
            'thumbnail_url' => $metadata['thumbnail'] ?? null,
            'language' => $transcriptLanguage,
            'raw_file_path' => 'transcripts/' . basename($vttFile),
            'full_text' => $fullText,
            'segments_json' => $segments,
            'word_count' => str_word_count($fullText),
            'chapters' => $this->extractChapters($metadata),
        ];
    }

    public function downloadFormats(Video $video): array
    {
        $metadata = $this->fetchMetadata($video->url);
        $formats = is_array($metadata['formats'] ?? null) ? $metadata['formats'] : [];

        $videoFormats = collect($formats)
            ->filter(fn ($format) => $this->isVideoFormat($format))
            ->map(fn ($format) => $this->formatSummary($format, 'video'))
            ->sortByDesc(fn ($format) => [$format['height'] ?? 0, $format['filesize'] ?? 0])
            ->values()
            ->all();

        $audioFormats = collect($formats)
            ->filter(fn ($format) => $this->isAudioFormat($format))
            ->map(fn ($format) => $this->formatSummary($format, 'audio'))
            ->sortByDesc(fn ($format) => [$format['abr'] ?? 0, $format['filesize'] ?? 0])
            ->values()
            ->all();

        return [
            'title' => $metadata['title'] ?? $video->title,
            'video' => array_values(array_filter($videoFormats, fn ($format) => $format['format_id'] !== null)),
            'audio' => array_values(array_filter($audioFormats, fn ($format) => $format['format_id'] !== null)),
            'defaults' => [
                'video' => 'best',
                'audio' => 'bestaudio',
            ],
        ];
    }

    public function downloadMedia(Video $video, string $type, string $formatId): array
    {
        $metadata = $this->fetchMetadata($video->url);
        $this->assertDownloadFormatAllowed($metadata, $type, $formatId);

        $directory = storage_path('app/downloads/' . uniqid('media-', true));
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $outputTemplate = $directory . DIRECTORY_SEPARATOR . $video->youtube_id . '.%(ext)s';
        $arguments = $type === 'audio'
            ? $this->audioDownloadArguments($formatId, $outputTemplate, $video->url)
            : $this->videoDownloadArguments($formatId, $metadata, $outputTemplate, $video->url);

        $process = $this->runYtDlp($arguments, 1200);

        if (!$process->isSuccessful()) {
            $this->deleteDirectory($directory);
            throw new RuntimeException($this->ytDlpErrorMessage('Unable to download YouTube media', $process));
        }

        $file = $this->downloadedMediaFile($directory, $video->youtube_id);

        if ($file === null) {
            $this->deleteDirectory($directory);
            throw new RuntimeException('The media file could not be found after download.');
        }

        return [
            'path' => $file,
            'filename' => $this->downloadFilename($metadata, $video, $type, $file),
        ];
    }

    public function generateChapterThumbnails(Video $video): array
    {
        $chapters = is_array($video->chapters_json) ? $video->chapters_json : [];
        if ($chapters === []) {
            return [];
        }

        foreach ($chapters as $index => &$chapter) {
            $start = max(0, (float) ($chapter['start_time'] ?? 0));
            $nextStart = isset($chapters[$index + 1]['start_time'])
                ? (float) $chapters[$index + 1]['start_time']
                : null;
            $end = isset($chapter['end_time']) ? (float) $chapter['end_time'] : 0;

            if ($end <= $start) {
                $end = $nextStart ?? (float) ($video->duration ?? $start);
            }

            $chapter['start_time'] = $start;
            $chapter['end_time'] = max($start, $end);
            $chapter['duration'] = $chapter['end_time'] - $start;
        }
        unset($chapter);

        $workDirectory = storage_path('app/chapter-thumbnail-work/' . uniqid('video-', true));
        $outputDirectory = storage_path('app/chapter-thumbnails/' . $video->id);
        $outputTemplate = $workDirectory . DIRECTORY_SEPARATOR . $video->youtube_id . '.%(ext)s';

        if (!is_dir($workDirectory)) {
            mkdir($workDirectory, 0755, true);
        }
        if (!is_dir($outputDirectory)) {
            mkdir($outputDirectory, 0755, true);
        }

        try {
            $download = $this->runYtDlp([
                '--no-playlist',
                '-f',
                'bestvideo[height<=360]/worstvideo',
                '--no-part',
                '-o',
                $outputTemplate,
                $video->url,
            ], 1200);

            if (!$download->isSuccessful()) {
                throw new RuntimeException($this->ytDlpErrorMessage('Unable to download video for chapter thumbnails', $download));
            }

            $source = $this->downloadedMediaFile($workDirectory, $video->youtube_id);
            if ($source === null) {
                throw new RuntimeException('The temporary chapter video could not be found.');
            }

            $generated = 0;
            foreach ($chapters as $index => &$chapter) {
                $start = max(0, (float) ($chapter['start_time'] ?? 0));
                $duration = max(0, (float) ($chapter['duration'] ?? 0));
                $seek = $start + min(1, $duration * 0.1);
                $filename = sprintf('%03d.jpg', $index);
                $output = $outputDirectory . DIRECTORY_SEPARATOR . $filename;

                $ffmpeg = new Process([
                    'ffmpeg',
                    '-y',
                    '-ss',
                    number_format($seek, 3, '.', ''),
                    '-i',
                    $source,
                    '-frames:v',
                    '1',
                    '-vf',
                    'scale=480:-2',
                    '-q:v',
                    '3',
                    $output,
                ]);
                $ffmpeg->setTimeout(90);
                $ffmpeg->run();

                if (!$ffmpeg->isSuccessful() || !is_file($output) || filesize($output) === 0) {
                    logger()->warning('Unable to extract one chapter thumbnail', [
                        'source' => 'youtube',
                        'video_id' => $video->id,
                        'chapter_index' => $index,
                        'error' => trim($ffmpeg->getErrorOutput()),
                    ]);
                    continue;
                }

                $version = filemtime($output) ?: time();
                $chapter['thumbnail_url'] = "/api/videos/{$video->id}/chapters/{$index}/thumbnail?v={$version}";
                $generated++;
            }
            unset($chapter);

            if ($generated === 0) {
                throw new RuntimeException('No chapter thumbnail could be generated.');
            }

            return $chapters;
        } finally {
            $this->deleteDirectory($workDirectory);
        }
    }

    private function isVideoFormat(array $format): bool
    {
        $formatId = $format['format_id'] ?? null;
        $vcodec = $format['vcodec'] ?? 'none';
        $height = $format['height'] ?? null;

        return is_string($formatId)
            && $vcodec !== 'none'
            && is_numeric($height)
            && (int) $height > 0;
    }

    private function isAudioFormat(array $format): bool
    {
        $formatId = $format['format_id'] ?? null;
        $vcodec = $format['vcodec'] ?? 'none';
        $acodec = $format['acodec'] ?? 'none';

        return is_string($formatId)
            && $vcodec === 'none'
            && $acodec !== 'none';
    }

    private function formatSummary(array $format, string $type): array
    {
        $filesize = $format['filesize'] ?? $format['filesize_approx'] ?? null;

        return [
            'format_id' => $format['format_id'] ?? null,
            'type' => $type,
            'label' => $this->formatLabel($format, $type),
            'ext' => $format['ext'] ?? null,
            'resolution' => $format['resolution'] ?? null,
            'height' => isset($format['height']) ? (int) $format['height'] : null,
            'fps' => isset($format['fps']) ? (float) $format['fps'] : null,
            'abr' => isset($format['abr']) ? (float) $format['abr'] : null,
            'vcodec' => $format['vcodec'] ?? null,
            'acodec' => $format['acodec'] ?? null,
            'filesize' => is_numeric($filesize) ? (int) $filesize : null,
        ];
    }

    private function formatLabel(array $format, string $type): string
    {
        if ($type === 'audio') {
            $abr = isset($format['abr']) ? round((float) $format['abr']) . ' kbps' : 'audio';
            $ext = $format['ext'] ?? 'audio';

            return trim("{$abr} · {$ext}");
        }

        $height = isset($format['height']) ? (int) $format['height'] . 'p' : ($format['resolution'] ?? 'video');
        $fps = isset($format['fps']) && (float) $format['fps'] > 30 ? ' ' . round((float) $format['fps']) . 'fps' : '';
        $ext = $format['ext'] ?? 'video';
        $audio = ($format['acodec'] ?? 'none') === 'none' ? ' + audio' : '';

        return trim("{$height}{$fps} · {$ext}{$audio}");
    }

    private function assertDownloadFormatAllowed(array $metadata, string $type, string $formatId): void
    {
        if ($type === 'video' && $formatId === 'best') {
            return;
        }

        if ($type === 'audio' && $formatId === 'bestaudio') {
            return;
        }

        $formats = is_array($metadata['formats'] ?? null) ? $metadata['formats'] : [];
        $allowed = collect($formats)->contains(function ($format) use ($type, $formatId) {
            if (($format['format_id'] ?? null) !== $formatId) {
                return false;
            }

            return $type === 'audio'
                ? $this->isAudioFormat($format)
                : $this->isVideoFormat($format);
        });

        if (!$allowed) {
            throw new RuntimeException('Requested media format is not available.');
        }
    }

    private function audioDownloadArguments(string $formatId, string $outputTemplate, string $url): array
    {
        $selector = $formatId === 'bestaudio' ? 'bestaudio/best' : "{$formatId}/bestaudio/best";

        return [
            '--no-playlist',
            '-f',
            $selector,
            '--extract-audio',
            '--audio-format',
            'mp3',
            '--audio-quality',
            '0',
            '-o',
            $outputTemplate,
            $url,
        ];
    }

    private function videoDownloadArguments(string $formatId, array $metadata, string $outputTemplate, string $url): array
    {
        $selector = $formatId === 'best'
            ? 'bestvideo+bestaudio/best'
            : $this->videoFormatNeedsAudio($metadata, $formatId);

        return [
            '--no-playlist',
            '-f',
            $selector,
            '--merge-output-format',
            'mp4',
            '-o',
            $outputTemplate,
            $url,
        ];
    }

    private function videoFormatNeedsAudio(array $metadata, string $formatId): string
    {
        $formats = is_array($metadata['formats'] ?? null) ? $metadata['formats'] : [];

        foreach ($formats as $format) {
            if (($format['format_id'] ?? null) === $formatId) {
                return ($format['acodec'] ?? 'none') === 'none'
                    ? "{$formatId}+bestaudio/{$formatId}"
                    : $formatId;
            }
        }

        return $formatId;
    }

    private function downloadedMediaFile(string $directory, string $youtubeId): ?string
    {
        $files = glob($directory . DIRECTORY_SEPARATOR . $youtubeId . '.*') ?: [];
        sort($files);

        foreach ($files as $file) {
            if (is_file($file) && filesize($file) > 0) {
                return $file;
            }
        }

        return null;
    }

    private function downloadFilename(array $metadata, Video $video, string $type, string $file): string
    {
        $title = $metadata['title'] ?? $video->title ?? $video->youtube_id;
        $safeTitle = preg_replace('/[^A-Za-z0-9._-]+/', '-', $title) ?: $video->youtube_id;
        $safeTitle = trim($safeTitle, '-_.') ?: $video->youtube_id;
        $extension = pathinfo($file, PATHINFO_EXTENSION) ?: ($type === 'audio' ? 'mp3' : 'mp4');

        return "{$safeTitle}-{$video->youtube_id}.{$extension}";
    }

    private function deleteDirectory(string $directory): void
    {
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        if (is_dir($directory)) {
            rmdir($directory);
        }
    }

    public function diagnoseMetadata(string $url): array
    {
        $process = $this->runYtDlp([
            '--dump-json',
            '--no-download',
            $url,
        ], 120);

        $metadata = null;
        if ($process->isSuccessful()) {
            try {
                $metadata = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $metadata = null;
            }
        }

        $videoId = is_array($metadata) ? ($metadata['id'] ?? null) : null;
        $videoLanguage = is_array($metadata) ? ($this->normalizeLanguageCode($metadata['language'] ?? null) ?? 'en') : 'en';
        $subtitleDiagnostic = null;

        if (is_string($videoId) && preg_match('/^[a-zA-Z0-9_-]{11}$/', $videoId)) {
            $subtitleDiagnostic = $this->diagnoseSubtitles($url, $videoId, $this->subtitleLanguageCandidates($metadata ?? [], null, $videoLanguage));
        }

        return [
            'ok' => $process->isSuccessful() && ($subtitleDiagnostic['ok'] ?? true),
            'exit_code' => $process->getExitCode(),
            'cookies' => $this->cookiesDiagnostics(),
            'title' => is_array($metadata) ? ($metadata['title'] ?? null) : null,
            'error' => $this->trimDiagnosticOutput($process->getErrorOutput()),
            'metadata' => [
                'ok' => $process->isSuccessful(),
                'exit_code' => $process->getExitCode(),
                'error' => $this->trimDiagnosticOutput($process->getErrorOutput()),
            ],
            'subtitles' => $subtitleDiagnostic,
        ];
    }

    public function fetchMetadata(string $url): array
    {
        $process = $this->runYtDlp([
            '--dump-json',
            '--no-download',
            $url,
        ], 120);

        if (!$process->isSuccessful()) {
            $message = $this->ytDlpErrorMessage('Unable to fetch YouTube metadata', $process);
            logger()->error('YouTube metadata fetch failed', [
                'source' => 'youtube',
                'url' => $url,
                'exit_code' => $process->getExitCode(),
                'error' => $message,
            ]);

            throw new RuntimeException($message);
        }

        try {
            return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            logger()->error('Unable to decode YouTube metadata', [
                'source' => 'youtube',
                'url' => $url,
                'error' => $e->getMessage(),
                'output_preview' => substr($process->getOutput(), 0, 500),
            ]);

            throw new RuntimeException('Unable to decode YouTube metadata.', previous: $e);
        }
    }

    private function publishedAtFromMetadata(array $metadata): ?string
    {
        foreach (['timestamp', 'release_timestamp', 'modified_timestamp'] as $key) {
            if (isset($metadata[$key]) && is_numeric($metadata[$key])) {
                return gmdate(DATE_ATOM, (int) $metadata[$key]);
            }
        }

        foreach (['upload_date', 'release_date', 'modified_date'] as $key) {
            $value = $metadata[$key] ?? null;
            if (!is_string($value) || !preg_match('/^\d{8}$/', $value)) {
                continue;
            }

            $date = \DateTimeImmutable::createFromFormat('!Ymd', $value, new \DateTimeZone('UTC'));
            if ($date !== false) {
                return $date->format(DATE_ATOM);
            }
        }

        return null;
    }

    private function downloadSubtitles(string $url, string $youtubeId, array $languages): string
    {
        $outputTemplate = $this->storagePath . DIRECTORY_SEPARATOR . '%(id)s.%(ext)s';
        $errors = [];

        foreach ($languages as $subtitleLanguage) {
            $process = $this->runYtDlp([
                '--write-subs',
                '--write-auto-subs',
                '--sub-lang',
                $subtitleLanguage,
                '--skip-download',
                '--sub-format',
                'vtt',
                '-o',
                $outputTemplate,
                $url,
            ], 240);

            if ($this->findSubtitleFile($youtubeId, $subtitleLanguage) !== null) {
                return $subtitleLanguage;
            }

            $error = $process->isSuccessful()
                ? "No subtitles found for '{$subtitleLanguage}'."
                : $this->ytDlpErrorMessage("Unable to download YouTube subtitles for '{$subtitleLanguage}'", $process);

            $errors[] = $error;

            logger()->warning('YouTube subtitle retrieval failed', [
                'source' => 'youtube',
                'youtube_id' => $youtubeId,
                'language' => $subtitleLanguage,
                'exit_code' => $process->getExitCode(),
                'error' => $error,
            ]);

            if ($this->isRateLimited($process)) {
                throw new RuntimeException(end($errors));
            }
        }

        throw new RuntimeException(implode(' ', $errors));
    }

    private function diagnoseSubtitles(string $url, string $youtubeId, array $languages): array
    {
        $diagnosticPath = storage_path('app/yt-dlp-diagnostics/' . uniqid('subtitles-', true));
        if (!is_dir($diagnosticPath)) {
            mkdir($diagnosticPath, 0755, true);
        }

        $errors = [];

        try {
            foreach (array_slice($languages, 0, 3) as $subtitleLanguage) {
                $process = $this->runYtDlp([
                    '--write-subs',
                    '--write-auto-subs',
                    '--sub-lang',
                    $subtitleLanguage,
                    '--skip-download',
                    '--sub-format',
                    'vtt',
                    '-o',
                    $diagnosticPath . DIRECTORY_SEPARATOR . '%(id)s.%(ext)s',
                    $url,
                ], 180);

                if ($this->findSubtitleFileInPath($diagnosticPath, $youtubeId, $subtitleLanguage) !== null) {
                    return [
                        'ok' => true,
                        'language' => $subtitleLanguage,
                        'exit_code' => $process->getExitCode(),
                        'error' => $this->trimDiagnosticOutput($process->getErrorOutput()),
                    ];
                }

                $errors[] = $process->isSuccessful()
                    ? "No subtitles found for '{$subtitleLanguage}'."
                    : $this->ytDlpErrorMessage("Unable to download YouTube subtitles for '{$subtitleLanguage}'", $process);

                if ($this->isRateLimited($process)) {
                    break;
                }
            }

            return [
                'ok' => false,
                'language' => null,
                'exit_code' => null,
                'error' => $this->trimDiagnosticOutput(implode(' ', $errors)),
            ];
        } finally {
            foreach (glob($diagnosticPath . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }

            if (is_dir($diagnosticPath)) {
                rmdir($diagnosticPath);
            }
        }
    }

    private function subtitleLanguageCandidates(array $metadata, ?string $preferredLanguage, string $videoLanguage): array
    {
        $availableLanguages = $this->availableSubtitleLanguages($metadata);
        $candidates = [];

        foreach (array_filter([$preferredLanguage, $videoLanguage]) as $language) {
            array_push($candidates, ...$this->matchingSubtitleLanguages($availableLanguages, $language));
            $candidates[] = $language;
        }

        array_push($candidates, ...$this->manualSubtitleLanguages($metadata));
        $candidates[] = 'en';

        return array_values(array_unique(array_filter($candidates)));
    }

    private function availableSubtitleLanguages(array $metadata): array
    {
        return array_values(array_unique(array_filter(array_merge(
            $this->manualSubtitleLanguages($metadata),
            $this->subtitleKeys($metadata['automatic_captions'] ?? [])
        ))));
    }

    private function manualSubtitleLanguages(array $metadata): array
    {
        return $this->subtitleKeys($metadata['subtitles'] ?? []);
    }

    public function extractChapters(array $metadata): array
    {
        $chapters = $metadata['chapters'] ?? [];

        if (!is_array($chapters) || empty($chapters)) {
            return [];
        }

        $normalized = array_values(array_filter(array_map(function ($chapter) {
            if (!isset($chapter['title']) || !isset($chapter['start_time'])) {
                return null;
            }

            return [
                'title' => trim($chapter['title']),
                'start_time' => (float) $chapter['start_time'],
                'end_time' => isset($chapter['end_time']) && is_numeric($chapter['end_time'])
                    ? (float) $chapter['end_time']
                    : null,
            ];
        }, $chapters)));

        $videoDuration = isset($metadata['duration']) && is_numeric($metadata['duration'])
            ? (float) $metadata['duration']
            : null;

        foreach ($normalized as $index => &$chapter) {
            $nextStart = $normalized[$index + 1]['start_time'] ?? null;
            $end = $chapter['end_time'];

            if ($end === null || $end <= $chapter['start_time']) {
                $end = $nextStart ?? $videoDuration ?? $chapter['start_time'];
            }

            $chapter['end_time'] = max($chapter['start_time'], (float) $end);
            $chapter['duration'] = $chapter['end_time'] - $chapter['start_time'];
        }
        unset($chapter);

        return $normalized;
    }

    private function subtitleKeys(mixed $subtitles): array
    {
        if (!is_array($subtitles)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn ($language) => $this->normalizeLanguageCode((string) $language), array_keys($subtitles))
        ));
    }

    private function matchingSubtitleLanguages(array $availableLanguages, string $wantedLanguage): array
    {
        return array_values(array_filter(
            $availableLanguages,
            fn ($language) => $language === $wantedLanguage || str_starts_with($language, $wantedLanguage . '-')
        ));
    }

    private function runYtDlp(array $arguments, int $timeout): Process
    {
        $temporaryCookiesPath = $this->temporaryCookiesPath();

        try {
            $process = new Process($this->ytDlpCommand($arguments, $temporaryCookiesPath));
            $process->setTimeout($timeout);
            $process->run();

            return $process;
        } finally {
            if ($temporaryCookiesPath !== null && is_file($temporaryCookiesPath)) {
                unlink($temporaryCookiesPath);
            }
        }
    }

    private function ytDlpCommand(array $arguments, ?string $cookiesPath = null): array
    {
        return array_merge([$this->ytDlpPath], $this->networkArguments(), $this->cookiesArguments($cookiesPath), $arguments);
    }

    private function networkArguments(): array
    {
        $arguments = [
            '--no-update',
            '--retries',
            (string) $this->retries,
            '--fragment-retries',
            (string) $this->retries,
            '--extractor-retries',
            (string) $this->retries,
        ];

        if ($this->sleepRequests > 0) {
            $arguments[] = '--sleep-requests';
            $arguments[] = (string) $this->sleepRequests;
        }

        if ($this->sleepSubtitles > 0) {
            $arguments[] = '--sleep-subtitles';
            $arguments[] = (string) $this->sleepSubtitles;
        }

        if ($this->retrySleep !== '') {
            $arguments[] = '--retry-sleep';
            $arguments[] = $this->retrySleep;
        }

        if ($this->jsRuntimes !== '') {
            $arguments[] = '--js-runtimes';
            $arguments[] = $this->jsRuntimes;
        }

        return $arguments;
    }

    private function isRateLimited(Process $process): bool
    {
        $error = $process->getErrorOutput();

        return str_contains($error, 'HTTP Error 429')
            || str_contains($error, 'Too Many Requests');
    }

    private function cookiesArguments(?string $cookiesPath = null): array
    {
        $cookiesPath ??= $this->cookiesPath;

        if (!$this->hasReadableCookiesFile($cookiesPath)) {
            return [];
        }

        return ['--cookies', $cookiesPath];
    }

    private function hasReadableCookiesFile(?string $path = null): bool
    {
        $path ??= $this->cookiesPath;

        return $path !== null
            && is_file($path)
            && is_readable($path);
    }

    private function temporaryCookiesPath(): ?string
    {
        if (!$this->hasReadableCookiesFile()) {
            return null;
        }

        $directory = storage_path('app/yt-dlp-cookies');
        if (!is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        $temporaryPath = tempnam($directory, 'cookies-');

        if ($temporaryPath === false || !copy($this->cookiesPath, $temporaryPath)) {
            return null;
        }

        chmod($temporaryPath, 0600);

        return $temporaryPath;
    }

    private function ytDlpErrorMessage(string $prefix, Process $process): string
    {
        $error = trim($process->getErrorOutput());
        $message = $prefix . ($error !== '' ? ': ' . $error : '.');

        if (str_contains($error, 'Sign in to confirm')) {
            $cookies = $this->cookiesDiagnostics();

            if ($cookies['using_cookies']) {
                $message .= " Cookies file was used by yt-dlp ({$cookies['size']} bytes), so the imported YouTube session is probably expired, incomplete, or not logged in.";
            } else {
                $message .= ' No readable cookies file was found. Import cookies.txt in the admin or configure YOUTUBE_COOKIES_BASE64/YOUTUBE_COOKIES_PATH.';
            }
        }

        if (str_contains($error, 'HTTP Error 429') || str_contains($error, 'Too Many Requests')) {
            $message .= ' YouTube is rate-limiting this server right now. Wait a few minutes, keep cookies configured, then retry from the admin. If it persists, refresh the YouTube cookies from a logged-in browser.';
        }

        if (str_contains($error, 'n challenge solving failed')) {
            $message .= ' YouTube requires JavaScript challenge solving. The Docker image must include Node.js and yt-dlp EJS components; rebuild the app image after deploying this fix.';
        }

        return $message;
    }

    private function cookiesDiagnostics(): array
    {
        $exists = $this->cookiesPath !== null && is_file($this->cookiesPath);
        $readable = $exists && is_readable($this->cookiesPath);

        return [
            'path' => $this->cookiesPath,
            'exists' => $exists,
            'readable' => $readable,
            'using_cookies' => $readable,
            'size' => $exists ? (filesize($this->cookiesPath) ?: 0) : 0,
            'updated_at' => $exists ? date(DATE_ATOM, filemtime($this->cookiesPath) ?: time()) : null,
        ];
    }

    private function trimDiagnosticOutput(string $output): string
    {
        $output = preg_replace('/\s+/', ' ', trim($output)) ?? '';

        return mb_substr($output, 0, 1200);
    }

    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1) {
            return $path;
        }

        return base_path($path);
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

    private function findSubtitleFile(string $youtubeId, string $language): ?string
    {
        return $this->findSubtitleFileInPath($this->storagePath, $youtubeId, $language);
    }

    private function findSubtitleFileInPath(string $path, string $youtubeId, string $language): ?string
    {
        $candidates = [
            $path . DIRECTORY_SEPARATOR . "{$youtubeId}.{$language}.vtt",
            $path . DIRECTORY_SEPARATOR . "{$youtubeId}.en.vtt",
            $path . DIRECTORY_SEPARATOR . "{$youtubeId}.fr.vtt",
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $files = glob($path . DIRECTORY_SEPARATOR . $youtubeId . '*.vtt') ?: [];
        sort($files);

        return $files[0] ?? null;
    }

    private function parseVtt(string $vtt): array
    {
        $segments = [];
        $lines = preg_split('/\R/', $vtt) ?: [];
        $currentSegment = null;

        foreach ($lines as $line) {
            $line = trim($line);

            if (preg_match('/^((?:\d{2}:)?\d{2}:\d{2}[\.,]\d{3})\s+-->\s+((?:\d{2}:)?\d{2}:\d{2}[\.,]\d{3})/', $line, $matches)) {
                if ($currentSegment !== null && trim($currentSegment['text']) !== '') {
                    $segments[] = $currentSegment;
                }

                $currentSegment = [
                    'start' => $this->vttTimeToSeconds($matches[1]),
                    'end' => $this->vttTimeToSeconds($matches[2]),
                    'text' => '',
                ];

                continue;
            }

            if ($currentSegment === null || $line === '' || $this->isVttMetadataLine($line)) {
                continue;
            }

            $text = $this->cleanVttText($line);
            if ($text === '') {
                continue;
            }

            $currentSegment['text'] .= ($currentSegment['text'] === '' ? '' : ' ') . $text;
        }

        if ($currentSegment !== null && trim($currentSegment['text']) !== '') {
            $segments[] = $currentSegment;
        }

        return $this->dedupeConsecutiveSegments($segments);
    }

    private function isVttMetadataLine(string $line): bool
    {
        return str_starts_with($line, 'WEBVTT')
            || str_starts_with($line, 'NOTE')
            || str_starts_with($line, 'STYLE')
            || str_starts_with($line, 'REGION')
            || preg_match('/^\d+$/', $line);
    }

    private function cleanVttText(string $line): string
    {
        $line = preg_replace('/<[^>]*>/', '', $line) ?? '';
        $line = html_entity_decode($line, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $line = preg_replace('/\s+/', ' ', $line) ?? '';

        return trim($line);
    }

    private function dedupeConsecutiveSegments(array $segments): array
    {
        if (empty($segments)) {
            return [];
        }

        // Étape 1: enlever les doublons exacts et les buildup YouTube
        $cleaned = [];
        $previousText = null;

        foreach ($segments as $segment) {
            $currentText = mb_strtolower(trim($segment['text']));

            if ($currentText === '' || $currentText === $previousText) {
                continue;
            }

            // YouTube buildup: si le précédent est préfixe du courant, remplacer
            if ($previousText !== null) {
                if (str_starts_with($currentText, $previousText)) {
                    array_pop($cleaned);
                    $cleaned[] = $segment;
                    $previousText = $currentText;
                    continue;
                }
                if (str_starts_with($previousText, $currentText)) {
                    continue;
                }
            }

            $cleaned[] = $segment;
            $previousText = $currentText;
        }

        // Étape 2: enlever le chevauchement YouTube (chaque segment commence par la fin du précédent)
        $result = [];
        $prevText = '';

        foreach ($cleaned as $i => $segment) {
            $text = trim($segment['text']);

            if ($i === 0) {
                $result[] = $segment;
                $prevText = $text;
                continue;
            }

            // Chercher où commence la nouvelle info dans le segment courant
            // Le segment courant commence généralement par la FIN du précédent
            $newText = $this->removeOverlap($prevText, $text);

            if (trim($newText) !== '') {
                $segment['text'] = $newText;
                $result[] = $segment;
                $prevText = $text; // Garder le texte original pour le prochain chevauchement
            }
        }

        return $result;
    }

    private function removeOverlap(string $previous, string $current): string
    {
        $prevWords = preg_split('/\s+/', trim($previous)) ?: [];
        $currWords = preg_split('/\s+/', trim($current)) ?: [];

        // Cherche le chevauchement maximum: combien de mots du début de $current
        // sont identiques à la fin de $previous
        $maxOverlap = min(count($prevWords), count($currWords));
        $bestOverlap = 0;

        for ($overlap = $maxOverlap; $overlap > 0; $overlap--) {
            $prevEnd = array_slice($prevWords, -$overlap);
            $currStart = array_slice($currWords, 0, $overlap);

            if (mb_strtolower(implode(' ', $prevEnd)) === mb_strtolower(implode(' ', $currStart))) {
                $bestOverlap = $overlap;
                break;
            }
        }

        if ($bestOverlap > 0) {
            $newWords = array_slice($currWords, $bestOverlap);
            return implode(' ', $newWords);
        }

        return $current;
    }

    private function vttTimeToSeconds(string $time): float
    {
        if (!preg_match('/^(?:(\d{2}):)?(\d{2}):(\d{2})[\.,](\d{3})$/', $time, $matches)) {
            return 0.0;
        }

        $hours = (int) ($matches[1] ?? 0);
        $minutes = (int) $matches[2];
        $seconds = (int) $matches[3];
        $milliseconds = (int) $matches[4];

        return ($hours * 3600) + ($minutes * 60) + $seconds + ($milliseconds / 1000);
    }
}
