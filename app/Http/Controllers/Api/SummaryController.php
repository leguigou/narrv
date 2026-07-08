<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Models\Summary;
use App\Services\SummaryService;
use Illuminate\Http\Request;

class SummaryController extends Controller
{
    public function store(Request $request, $id)
    {
        $request->validate([
            'temperature' => 'numeric|min:0|max:1.5',
            'tone' => 'in:neutral,formal,casual,bullet_points',
            'length' => 'in:short,medium,long',
        ]);

        $video = Video::with('transcript')->findOrFail($id);
        $transcript = $video->transcript;

        if (!$transcript) {
            return response()->json(['error' => 'Transcript pas encore prêt'], 404);
        }

        $service = new SummaryService();
        $content = $service->generate(
            $transcript->full_text,
            $request->temperature ?? 0.3,
            $request->tone ?? 'neutral',
            $request->length ?? 'medium'
        );

        $summary = Summary::create([
            'transcript_id' => $transcript->id,
            'content' => $content,
            'model' => config('services.deepseek.model'),
            'temperature' => $request->temperature ?? 0.3,
            'tone' => $request->tone ?? 'neutral',
            'length' => $request->length ?? 'medium',
        ]);

        return response()->json($summary);
    }

    public function index($id)
    {
        $video = Video::with('transcript.summaries')->findOrFail($id);
        return response()->json($video->transcript?->summaries ?? []);
    }
}
