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

    public function __construct()
    {
        $this->ytDlpPath = (string) config('services.youtube.yt_dlp_path', 'yt-dlp');
        $this->storagePath = storage_path('app/transcripts');
        $cookiesPath = config('services.youtube.cookies_path');
        $this->cookiesPath = is_string($cookiesPath) && trim($cookiesPath) !== '' ? $cookiesPath : null;

        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    public function fetchTranscript(Video $video): array
    {
        $metadata = $this->fetchMetadata($video->url);
        $language = $metadata['language'] ?? 'en';

        $this->downloadSubtitles($video->url, $language);

        $vttFile = $this->findSubtitleFile($video->youtube_id, $language);

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

    private function downloadSubtitles(string $url, string $language): void
    {
        $outputTemplate = $this->storagePath . DIRECTORY_SEPARATOR . '%(id)s.%(ext)s';
        $languages = implode(',', array_values(array_unique(array_filter([$language, 'en', 'fr']))));

        $process = new Process($this->ytDlpCommand([
            '--write-subs',
            '--write-auto-subs',
            '--sub-lang',
            $languages,
            '--skip-download',
            '--sub-format',
            'vtt',
            '-o',
            $outputTemplate,
            $url,
        ]));
        $process->setTimeout(180);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException($this->ytDlpErrorMessage('Unable to download YouTube subtitles', $process));
        }
    }

    private function ytDlpCommand(array $arguments): array
    {
        return array_merge([$this->ytDlpPath], $this->cookiesArguments(), $arguments);
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

        if (
            !$this->hasReadableCookiesFile()
            && str_contains($error, 'Sign in to confirm')
        ) {
            $message .= ' Configure YOUTUBE_COOKIES_BASE64 or YOUTUBE_COOKIES_PATH so yt-dlp can use an authenticated YouTube session.';
        }

        return $message;
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
        $deduped = [];
        $previousText = null;

        foreach ($segments as $segment) {
            $normalizedText = mb_strtolower(trim($segment['text']));

            if ($normalizedText === '' || $normalizedText === $previousText) {
                continue;
            }

            $deduped[] = $segment;
            $previousText = $normalizedText;
        }

        return $deduped;
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
