<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Summary;
use App\Models\Video;
use App\Services\SummaryService;
use Illuminate\Http\Request;
use RuntimeException;

class SummaryController extends Controller
{
    public function store(Request $request, $id)
    {
        $validated = $request->validate([
            'temperature' => 'nullable|numeric|min:0|max:1.5',
            'tone' => 'nullable|in:neutral,formal,casual,bullet_points',
            'length' => 'nullable|in:short,medium,long',
            'language' => 'nullable|string|in:en,fr,es,it,de',
        ]);

        $video = Video::with('transcript')->findOrFail($id);
        $transcript = $video->transcript;

        if (!$transcript || trim((string) $transcript->full_text) === '') {
            return response()->json(['error' => 'Transcript pas encore pret'], 404);
        }

        $temperature = (float) ($validated['temperature'] ?? 0.3);
        $tone = $validated['tone'] ?? 'neutral';
        $length = $validated['length'] ?? 'medium';
        $language = $validated['language'] ?? 'fr';

        try {
            $service = new SummaryService();
            $content = $service->generate($transcript->full_text, $temperature, $tone, $length, $language);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        $summary = Summary::create([
            'transcript_id' => $transcript->id,
            'content' => $content,
            'model' => config('services.deepseek.model'),
            'temperature' => $temperature,
            'tone' => $tone,
            'length' => $length,
            'language' => $language,
        ]);

        return response()->json($summary);
    }

    public function index($id)
    {
        $video = Video::with('transcript.summaries')->findOrFail($id);

        return response()->json($video->transcript?->summaries ?? []);
    }
}
