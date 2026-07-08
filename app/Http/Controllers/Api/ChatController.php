<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Video;
use App\Services\DeepseekService;
use Illuminate\Http\Request;
use RuntimeException;

class ChatController extends Controller
{
    public function store(Request $request, $id)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $video = Video::with('transcript')->findOrFail($id);
        $transcript = $video->transcript;

        if (!$transcript || trim((string) $transcript->full_text) === '') {
            return response()->json(['error' => 'Transcript pas encore pret'], 404);
        }

        ChatMessage::create([
            'transcript_id' => $transcript->id,
            'role' => 'user',
            'content' => $validated['message'],
        ]);

        $history = ChatMessage::where('transcript_id', $transcript->id)
            ->latest()
            ->take(20)
            ->get()
            ->reverse()
            ->values();

        try {
            $deepseek = new DeepseekService();
            $response = $deepseek->chat($transcript->full_text, $history);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        $assistantMsg = ChatMessage::create([
            'transcript_id' => $transcript->id,
            'role' => 'assistant',
            'content' => $response,
        ]);

        return response()->json(['assistant' => $assistantMsg]);
    }

    public function index($id)
    {
        $video = Video::with('transcript')->findOrFail($id);

        if (!$video->transcript) {
            return response()->json([]);
        }

        $messages = ChatMessage::where('transcript_id', $video->transcript->id)
            ->oldest()
            ->limit(50)
            ->get();

        return response()->json($messages);
    }
}
