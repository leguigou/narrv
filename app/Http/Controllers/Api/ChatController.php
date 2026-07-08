<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Models\ChatMessage;
use App\Services\DeepseekService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function store(Request $request, $id)
    {
        $request->validate(['message' => 'required|string']);

        $video = Video::with('transcript')->findOrFail($id);
        $transcript = $video->transcript;

        if (!$transcript) {
            return response()->json(['error' => 'Transcript pas encore prêt'], 404);
        }

        // Sauvegarde le message user
        ChatMessage::create([
            'transcript_id' => $transcript->id,
            'role' => 'user',
            'content' => $request->message,
        ]);

        // Récupère historique
        $history = ChatMessage::where('transcript_id', $transcript->id)
            ->latest()->take(20)->get()->reverse();

        // Appelle DeepSeek
        $deepseek = new DeepseekService();
        $response = $deepseek->chat($transcript->full_text, $history, $request->message);

        // Sauvegarde la réponse
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
            ->oldest()->paginate(50);

        return response()->json($messages);
    }
}
