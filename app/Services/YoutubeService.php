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
    private int $retries;
    private string $retrySleep;

    public function __construct()
    {
        $this->ytDlpPath = (string) config('services.youtube.yt_dlp_path', 'yt-dlp');
        $this->storagePath = storage_path('app/transcripts');
        $cookiesPath = config('services.youtube.cookies_path');
        $this->cookiesPath = is_string($cookiesPath) && trim($cookiesPath) !== ''
            ? $this->resolvePath($cookiesPath)
            : null;
        $this->sleepRequests = max(0, (float) config('services.youtube.sleep_requests', 1));
        $this->retries = max(0, (int) config('services.youtube.retries', 5));
        $retrySleep = config('services.youtube.retry_sleep', 'http:exp=1:20');
        $this->retrySleep = is_string($retrySleep) ? trim($retrySleep) : '';

        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    public function fetchTranscript(Video $video): array
    {
        $metadata = $this->fetchMetadata($video->url);
        $language = $metadata['language'] ?? 'en';
        // Normalize regional language codes such as fr-FR to fr.
        $langBase = explode('-', $language)[0];

        $this->downloadSubtitles($video->url, $video->youtube_id, $langBase);

        $vttFile = $this->findSubtitleFile($video->youtube_id, $langBase);

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
            'thumbnail_url' => $metadata['thumbnail'] ?? null,
            'language' => $language,
            'raw_file_path' => 'transcripts/' . basename($vttFile),
            'full_text' => $fullText,
            'segments_json' => $segments,
            'word_count' => str_word_count($fullText),
        ];
    }

    public function diagnoseMetadata(string $url): array
    {
        $process = new Process($this->ytDlpCommand([
            '--dump-json',
            '--no-download',
            $url,
        ]));
        $process->setTimeout(120);
        $process->run();

        $metadata = null;
        if ($process->isSuccessful()) {
            try {
                $metadata = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $metadata = null;
            }
        }

        return [
            'ok' => $process->isSuccessful(),
            'exit_code' => $process->getExitCode(),
            'cookies' => $this->cookiesDiagnostics(),
            'title' => is_array($metadata) ? ($metadata['title'] ?? null) : null,
            'error' => $this->trimDiagnosticOutput($process->getErrorOutput()),
        ];
    }

    private function fetchMetadata(string $url): array
    {
        $process = new Process($this->ytDlpCommand([
            '--dump-json',
            '--no-download',
            $url,
        ]));
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException($this->ytDlpErrorMessage('Unable to fetch YouTube metadata', $process));
        }

        try {
            return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException('Unable to decode YouTube metadata.', previous: $e);
        }
    }

    private function downloadSubtitles(string $url, string $youtubeId, string $language): void
    {
        $outputTemplate = $this->storagePath . DIRECTORY_SEPARATOR . '%(id)s.%(ext)s';
        $languages = array_values(array_unique(array_filter([$language, 'en', 'fr'])));
        $errors = [];

        foreach ($languages as $subtitleLanguage) {
            $process = new Process($this->ytDlpCommand([
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
            ]));
            $process->setTimeout(240);
            $process->run();

            if ($this->findSubtitleFile($youtubeId, $subtitleLanguage) !== null) {
                return;
            }

            $errors[] = $process->isSuccessful()
                ? "No subtitles found for '{$subtitleLanguage}'."
                : $this->ytDlpErrorMessage("Unable to download YouTube subtitles for '{$subtitleLanguage}'", $process);
        }

        throw new RuntimeException(implode(' ', $errors));
    }

    private function ytDlpCommand(array $arguments): array
    {
        return array_merge([$this->ytDlpPath], $this->networkArguments(), $this->cookiesArguments(), $arguments);
    }

    private function networkArguments(): array
    {
        $arguments = [
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

        if ($this->retrySleep !== '') {
            $arguments[] = '--retry-sleep';
            $arguments[] = $this->retrySleep;
        }

        return $arguments;
    }

    private function cookiesArguments(): array
    {
        if (!$this->hasReadableCookiesFile()) {
            return [];
        }

        return ['--cookies', $this->cookiesPath];
    }

    private function hasReadableCookiesFile(): bool
    {
        return $this->cookiesPath !== null
            && is_file($this->cookiesPath)
            && is_readable($this->cookiesPath);
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

    private function findSubtitleFile(string $youtubeId, string $language): ?string
    {
        $candidates = [
            $this->storagePath . DIRECTORY_SEPARATOR . "{$youtubeId}.{$language}.vtt",
            $this->storagePath . DIRECTORY_SEPARATOR . "{$youtubeId}.en.vtt",
            $this->storagePath . DIRECTORY_SEPARATOR . "{$youtubeId}.fr.vtt",
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $files = glob($this->storagePath . DIRECTORY_SEPARATOR . $youtubeId . '*.vtt') ?: [];
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
