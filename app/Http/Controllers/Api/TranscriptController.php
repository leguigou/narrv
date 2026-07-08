<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Translation;
use App\Models\Video;
use App\Services\DeepseekService;
use Illuminate\Http\Request;
use RuntimeException;

class TranscriptController extends Controller
{
    public function show($id)
    {
        $video = Video::with('transcript')->findOrFail($id);

        if (!$video->transcript) {
            return response()->json(['error' => 'Transcript pas encore pret'], 404);
        }

        return response()->json($video->transcript);
    }

    public function download(Request $request, $id)
    {
        $request->validate([
            'format' => 'nullable|in:txt,vtt,srt',
        ]);

        $video = Video::with('transcript')->findOrFail($id);
        $transcript = $video->transcript;

        if (!$transcript) {
            return response()->json(['error' => 'Transcript pas encore pret'], 404);
        }

        $format = $request->query('format', 'txt');
        $segments = $transcript->segments_json ?? [];

        $content = match ($format) {
            'vtt' => $this->downloadableVtt($transcript->raw_file_path, $transcript->full_text, $segments),
            'srt' => $this->txtToSrt($transcript->full_text, $segments),
            default => $transcript->full_text,
        };

        $contentType = match ($format) {
            'vtt' => 'text/vtt',
            'srt' => 'application/x-subrip',
            default => 'text/plain',
        };

        return response($content)
            ->header('Content-Type', $contentType . '; charset=UTF-8')
            ->header('Content-Disposition', "attachment; filename=\"{$video->youtube_id}.{$format}\"");
    }

    public function translate(Request $request, $id)
    {
        $validated = $request->validate([
            'language' => 'required|string|in:en,fr,es,it,de',
        ]);

        $video = Video::with('transcript')->findOrFail($id);
        $transcript = $video->transcript;

        if (!$transcript || trim((string) $transcript->full_text) === '') {
            return response()->json(['error' => 'Transcript pas encore pret'], 404);
        }

        try {
            $deepseek = new DeepseekService();
            $translated = $deepseek->translate($transcript->full_text, $validated['language']);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        $translation = Translation::updateOrCreate(
            [
                'transcript_id' => $transcript->id,
                'target_language' => $validated['language'],
            ],
            [
                'content' => $translated,
                'model' => $deepseek->model,
            ]
        );

        return response()->json($translation);
    }

    private function downloadableVtt(?string $rawFilePath, ?string $text, array $segments): string
    {
        if ($rawFilePath) {
            $path = storage_path('app/' . $rawFilePath);
            if (is_file($path)) {
                return file_get_contents($path) ?: '';
            }
        }

        return $this->txtToVtt($text, $segments);
    }

    private function txtToVtt(?string $text, array $segments): string
    {
        if (!$segments) {
            return "WEBVTT\n\n00:00:00.000 --> 00:00:05.000\n{$text}\n";
        }

        $lines = ["WEBVTT\n"];
        foreach ($segments as $seg) {
            $start = $this->secondsToTimestamp((float) ($seg['start'] ?? 0), '.');
            $end = $this->secondsToTimestamp((float) ($seg['end'] ?? (($seg['start'] ?? 0) + 5)), '.');
            $lines[] = "{$start} --> {$end}\n{$seg['text']}\n";
        }

        return implode("\n", $lines);
    }

    private function txtToSrt(?string $text, array $segments): string
    {
        if (!$segments) {
            return "1\n00:00:00,000 --> 00:00:05,000\n{$text}\n";
        }

        $lines = [];
        foreach ($segments as $i => $seg) {
            $start = $this->secondsToTimestamp((float) ($seg['start'] ?? 0), ',');
            $end = $this->secondsToTimestamp((float) ($seg['end'] ?? (($seg['start'] ?? 0) + 5)), ',');
            $lines[] = ($i + 1) . "\n{$start} --> {$end}\n{$seg['text']}\n";
        }

        return implode("\n", $lines);
    }

    private function secondsToTimestamp(float $seconds, string $separator): string
    {
        $milliseconds = (int) round(($seconds - floor($seconds)) * 1000);
        $wholeSeconds = (int) floor($seconds);

        if ($milliseconds === 1000) {
            $wholeSeconds++;
            $milliseconds = 0;
        }

        return sprintf(
            '%02d:%02d:%02d%s%03d',
            intdiv($wholeSeconds, 3600),
            intdiv($wholeSeconds % 3600, 60),
            $wholeSeconds % 60,
            $separator,
            $milliseconds
        );
    }
}
