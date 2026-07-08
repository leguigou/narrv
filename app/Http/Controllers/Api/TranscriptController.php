<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Models\Translation;
use App\Services\DeepseekService;
use Illuminate\Http\Request;

class TranscriptController extends Controller
{
    public function show($id)
    {
        $video = Video::with('transcript')->findOrFail($id);
        return response()->json($video->transcript);
    }

    public function download(Request $request, $id)
    {
        $video = Video::with('transcript')->findOrFail($id);
        $transcript = $video->transcript;

        if (!$transcript) {
            return response()->json(['error' => 'Transcript pas encore prêt'], 404);
        }

        $format = $request->query('format', 'txt');

        $content = match ($format) {
            'vtt' => $transcript->raw_file_path ? file_get_contents(storage_path('app/' . $transcript->raw_file_path)) : $this->txtToVtt($transcript->full_text, $transcript->segments_json),
            'srt' => $this->txtToSrt($transcript->full_text, $transcript->segments_json),
            default => $transcript->full_text,
        };

        $ext = match ($format) {
            'vtt' => 'vtt', 'srt' => 'srt', default => 'txt',
        };

        return response($content)
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', "attachment; filename=\"{$video->youtube_id}.{$ext}\"");
    }

    public function translate(Request $request, $id)
    {
        $request->validate(['language' => 'required|string|in:en,fr,es,it,de']);

        $video = Video::with('transcript')->findOrFail($id);
        $transcript = $video->transcript;

        if (!$transcript) {
            return response()->json(['error' => 'Transcript pas encore prêt'], 404);
        }

        $deepseek = new DeepseekService();
        $translated = $deepseek->translate($transcript->full_text, $request->language);

        $translation = Translation::create([
            'transcript_id' => $transcript->id,
            'target_language' => $request->language,
            'content' => $translated,
            'model' => $deepseek->model,
        ]);

        return response()->json($translation);
    }

    private function txtToVtt($text, $segments)
    {
        if (!$segments) return "WEBVTT\n\n1\n00:00:00.000 --> 00:00:05.000\n{$text}";

        $lines = ["WEBVTT\n"];
        foreach ($segments as $i => $seg) {
            $start = $this->secondsToVtt($seg['start'] ?? 0);
            $end = $this->secondsToVtt($seg['end'] ?? ($seg['start'] ?? 0) + 5);
            $lines[] = ($i + 1) . "\n{$start} --> {$end}\n{$seg['text']}\n";
        }
        return implode("\n", $lines);
    }

    private function txtToSrt($text, $segments)
    {
        if (!$segments) return "1\n00:00:00,000 --> 00:00:05,000\n{$text}";

        $lines = [];
        foreach ($segments as $i => $seg) {
            $start = $this->secondsToSrt($seg['start'] ?? 0);
            $end = $this->secondsToSrt($seg['end'] ?? ($seg['start'] ?? 0) + 5);
            $lines[] = ($i + 1) . "\n{$start} --> {$end}\n{$seg['text']}\n";
        }
        return implode("\n", $lines);
    }

    private function secondsToVtt($sec): string
    {
        return sprintf('%02d:%02d:%02d.000', floor($sec / 3600), floor(($sec % 3600) / 60), $sec % 60);
    }

    private function secondsToSrt($sec): string
    {
        return sprintf('%02d:%02d:%02d,000', floor($sec / 3600), floor(($sec % 3600) / 60), $sec % 60);
    }
}
