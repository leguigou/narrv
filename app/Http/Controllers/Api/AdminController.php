<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessYoutubeVideo;
use App\Models\AdminSession;
use App\Models\ChatMessage;
use App\Models\Summary;
use App\Models\Translation;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'password' => 'required|string|max:255',
        ]);

        $adminPassword = (string) config('services.admin.password', '');

        if ($adminPassword === '') {
            return response()->json(['error' => 'ADMIN_PASSWORD is not configured'], 503);
        }

        if (!hash_equals($adminPassword, $request->password)) {
            return response()->json(['error' => 'Mot de passe incorrect'], 401);
        }

        AdminSession::where('expires_at', '<=', now())->delete();

        $token = Str::random(64);
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
        Video::query()->delete();

        $transcriptsPath = storage_path('app/transcripts');
        File::deleteDirectory($transcriptsPath);
        File::ensureDirectoryExists($transcriptsPath);

        return response()->json(['message' => 'Toutes les donnees ont ete supprimees']);
    }

    public function deleteVideo($id)
    {
        $video = Video::with('transcript')->findOrFail($id);
        $this->deleteTranscriptFile($video);
        $video->delete();

        return response()->json(['message' => 'Video supprimee']);
    }

    public function retryVideo($id)
    {
        $video = Video::findOrFail($id);
        $video->update(['status' => 'pending', 'error_message' => null]);
        ProcessYoutubeVideo::dispatch($video);

        return response()->json(['message' => 'Video relancee']);
    }

    private function deleteTranscriptFile(Video $video): void
    {
        $rawFilePath = $video->transcript?->raw_file_path;
        if (!$rawFilePath) {
            return;
        }

        $path = storage_path('app/' . $rawFilePath);
        if (is_file($path)) {
            File::delete($path);
        }
    }
}
