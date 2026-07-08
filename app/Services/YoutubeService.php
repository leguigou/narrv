<?php

namespace App\Services;

use App\Models\Video;

class YoutubeService
{
    private string $ytDlpPath = 'yt-dlp';
    private string $storagePath;

    public function __construct()
    {
        $this->storagePath = storage_path('app/transcripts');
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    public function fetchTranscript(Video $video): array
    {
        $outputTemplate = $this->storagePath . '/%(id)s.%(ext)s';

        // Récupère les métadonnées
        $jsonCommand = sprintf(
            '%s --dump-json --no-download "%s" 2>/dev/null',
            escapeshellcmd($this->ytDlpPath),
            escapeshellarg($video->url)
        );
        $jsonOutput = shell_exec($jsonCommand);

        if (!$jsonOutput) {
            throw new \RuntimeException('Impossible de récupérer les métadonnées YouTube');
        }

        $metadata = json_decode($jsonOutput, true);

        // Télécharge les sous-titres
        $subsCommand = sprintf(
            '%s --write-auto-subs --sub-lang en --skip-download --sub-format vtt -o "%s" "%s" 2>/dev/null',
            escapeshellcmd($this->ytDlpPath),
            $outputTemplate,
            escapeshellarg($video->url)
        );
        shell_exec($subsCommand);

        // Cherche le fichier .vtt
        $vttFile = $this->storagePath . '/' . $video->youtube_id . '.en.vtt';
        $rawVtt = '';
        $segments = [];

        if (file_exists($vttFile)) {
            $rawVtt = file_get_contents($vttFile);
            $segments = $this->parseVtt($rawVtt);
        } else {
            // Fallback: cherche un fichier .vtt sans langue
            $files = glob($this->storagePath . '/' . $video->youtube_id . '*.vtt');
            if (!empty($files)) {
                $vttFile = $files[0];
                $rawVtt = file_get_contents($vttFile);
                $segments = $this->parseVtt($rawVtt);
            }
        }

        $fullText = collect($segments)->pluck('text')->implode(' ');

        return [
            'title' => $metadata['title'] ?? null,
            'channel_name' => $metadata['channel'] ?? $metadata['uploader'] ?? null,
            'channel_url' => $metadata['channel_url'] ?? null,
            'duration' => $metadata['duration'] ?? null,
            'thumbnail_url' => $metadata['thumbnail'] ?? null,
            'language' => $metadata['language'] ?? 'en',
            'raw_file_path' => isset($vttFile) ? 'transcripts/' . basename($vttFile) : null,
            'full_text' => $fullText,
            'segments_json' => $segments,
            'word_count' => str_word_count($fullText),
        ];
    }

    private function parseVtt(string $vtt): array
    {
        $segments = [];
        $lines = explode("\n", $vtt);
        $currentSegment = null;

        foreach ($lines as $line) {
            $line = trim($line);

            // Détecte une ligne de timestamp: 00:01.234 --> 00:05.678
            if (preg_match('/(\d{2}:\d{2}(?::\d{2})?\.\d{3})\s*-->\s*(\d{2}:\d{2}(?::\d{2})?\.\d{3})/', $line, $matches)) {
                if ($currentSegment !== null) {
                    $segments[] = $currentSegment;
                }
                $currentSegment = [
                    'start' => $this->vttTimeToSeconds($matches[1]),
                    'end' => $this->vttTimeToSeconds($matches[2]),
                    'text' => '',
                ];
            } elseif ($currentSegment !== null && !empty($line) && !str_starts_with($line, 'WEBVTT') && !str_starts_with($line, 'NOTE')) {
                $currentSegment['text'] .= ($currentSegment['text'] ? ' ' : '') . $line;
            }
        }

        if ($currentSegment !== null) {
            $segments[] = $currentSegment;
        }

        return $segments;
    }

    private function vttTimeToSeconds(string $time): float
    {
        $parts = explode(':', str_replace('.', ':', $time));
        $count = count($parts);

        if ($count === 3) {
            return (int) $parts[0] * 3600 + (int) $parts[1] * 60 + (float) "{$parts[2]}.{$parts[3]}";
        }
        if ($count === 4) {
            // HH:MM:SS.mmm
            return (int) $parts[0] * 3600 + (int) $parts[1] * 60 + (int) $parts[2] + (int) $parts[3] / 1000;
        }
        return (int) $parts[0] * 60 + (float) "{$parts[1]}.{$parts[2]}";
    }
}
