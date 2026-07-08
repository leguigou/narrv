<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminSession;
use App\Models\Video;
use App\Models\Summary;
use App\Models\ChatMessage;
use App\Models\Translation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function login(Request $request)
    {
        $request->validate(['password' => 'required|string']);

        $adminPassword = env('ADMIN_PASSWORD', 'admin123');

        if ($request->password !== $adminPassword) {
            return response()->json(['error' => 'Mot de passe incorrect'], 401);
        }

        $token = Str::random(32);
        AdminSession::create([
            'token' => hash('sha256', $token),
            'expires_at' => now()->addHours(24),
        ]);

        return response()->json(['token' => $token]);
    }

    public function stats()
    {
        return response()->json([
            'videos_count' => Video::count(),
            'summaries_count' => Summary::count(),
            'chat_messages_count' => ChatMessage::count(),
            'translations_count' => Translation::count(),
            'pending_videos' => Video::where('status', 'pending')->count(),
            'processing_videos' => Video::where('status', 'processing')->count(),
            'error_videos' => Video::where('status', 'error')->count(),
            'ready_videos' => Video::where('status', 'ready')->count(),
        ]);
    }

    public function purgeAll()
    {
        // Supprime tout (cascade)
        Video::query()->delete();
        return response()->json(['message' => 'Toutes les données supprimées']);
    }

    public function deleteVideo($id)
    {
        Video::findOrFail($id)->delete();
        return response()->json(['message' => 'Vidéo supprimée']);
    }

    public function retryVideo($id)
    {
        $video = Video::findOrFail($id);
        $video->update(['status' => 'pending', 'error_message' => null]);
        \App\Jobs\ProcessYoutubeVideo::dispatch($video);
        return response()->json(['message' => 'Vidéo relancée']);
    }
}
