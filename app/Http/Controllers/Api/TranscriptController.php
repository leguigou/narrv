<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Translation;
use App\Models\Video;
use App\Services\DeepseekService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TranscriptController extends Controller
{
    public function show($id)
    {
        $video = Video::with('transcript')->findOrFail($id);

        if (!$video->transcript) {
            return response()->json(['error' => 'Transcript pas encore pret'], 404);
        }

        return response()
            ->json($video->transcript)
            ->header('Cache-Control', 'private, max-age=300');
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

        $sourceLanguage = $this->normalizeLanguage($transcript->language);
        $targetLanguage = $this->normalizeLanguage($validated['language']);

        if ($sourceLanguage !== null && $sourceLanguage === $targetLanguage) {
            return response()->json([
                'error' => 'La langue cible est identique a la langue source.',
                'source_language' => $sourceLanguage,
                'target_language' => $targetLanguage,
            ], 422);
        }

        if (str_contains((string) $request->header('Accept'), 'application/x-ndjson')) {
            return $this->streamTranslation($transcript, $targetLanguage, $sourceLanguage);
        }

        try {
            $deepseek = app(DeepseekService::class);
            $segments = $transcript->segments_json ?? [];
            $translatedSegments = $segments
                ? $deepseek->translateSegments($segments, $targetLanguage, $sourceLanguage)
                : [];
            $translated = $translatedSegments
                ? $this->segmentsToText($translatedSegments)
                : $deepseek->translate($transcript->full_text, $targetLanguage, $sourceLanguage);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        $translation = Translation::updateOrCreate(
            [
                'transcript_id' => $transcript->id,
                'target_language' => $targetLanguage,
            ],
            [
                'content' => $translated,
                'segments_json' => $translatedSegments ?: null,
                'model' => $deepseek->model,
            ]
        );

        return response()->json($translation);
    }

    private function streamTranslation($transcript, string $targetLanguage, ?string $sourceLanguage): StreamedResponse
    {
        return response()->stream(function () use ($transcript, $targetLanguage, $sourceLanguage): void {
            $emit = static function (array $event): void {
                echo json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            };

            try {
                $deepseek = app(DeepseekService::class);
                $segments = $transcript->segments_json ?? [];
                $emit([
                    'type' => 'start',
                    'total' => $segments
                        ? $deepseek->segmentTranslationChunkCount($segments)
                        : $deepseek->translationChunkCount($transcript->full_text),
                ]);
                $translatedSegments = [];
                if ($segments) {
                    $translatedSegments = $deepseek->translateSegments(
                        $segments,
                        $targetLanguage,
                        $sourceLanguage,
                        static function (array $chunkSegments, int $index, int $total) use ($emit): void {
                            $emit([
                                'type' => 'chunk',
                                'index' => $index,
                                'total' => $total,
                                'segments' => $chunkSegments,
                            ]);
                        }
                    );
                    $translated = $this->segmentsToText($translatedSegments);
                } else {
                    $translated = $deepseek->translate(
                        $transcript->full_text,
                        $targetLanguage,
                        $sourceLanguage,
                        static function (string $content, int $index, int $total) use ($emit): void {
                            $emit([
                                'type' => 'chunk',
                                'index' => $index,
                                'total' => $total,
                                'content' => $content,
                            ]);
                        }
                    );
                }

                $translation = Translation::updateOrCreate(
                    [
                        'transcript_id' => $transcript->id,
                        'target_language' => $targetLanguage,
                    ],
                    [
                        'content' => $translated,
                        'segments_json' => $translatedSegments ?: null,
                        'model' => $deepseek->model,
                    ]
                );

                $emit([
                    'type' => 'complete',
                    'translation' => $translation->only(['id', 'target_language', 'content', 'segments_json', 'model']),
                ]);
            } catch (Throwable $e) {
                report($e);
                $emit([
                    'type' => 'error',
                    'error' => $e instanceof RuntimeException
                        ? $e->getMessage()
                        : 'Traduction impossible. Consultez les logs admin pour le detail.',
                ]);
            }
        }, 200, [
            'Content-Type' => 'application/x-ndjson; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function translations(string $id): JsonResponse
    {
        $video = Video::with('transcript')->findOrFail($id);

        if (!$video->transcript) {
            return response()->json([]);
        }

        return response()->json(
            Translation::where('transcript_id', $video->transcript->id)
                ->get(['id', 'target_language', 'content', 'segments_json', 'model', 'created_at'])
        );
    }

    private function segmentsToText(array $segments): string
    {
        return collect($segments)
            ->pluck('text')
            ->filter(fn ($text) => is_string($text) && trim($text) !== '')
            ->implode(' ');
    }

    private function normalizeLanguage(?string $language): ?string
    {
        if (!is_string($language) || trim($language) === '') {
            return null;
        }

        $language = strtolower(str_replace('_', '-', trim($language)));

        return explode('-', $language)[0] ?: null;
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
