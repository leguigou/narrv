<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessYoutubeVideo;
use App\Models\AdminSession;
use App\Models\ChatMessage;
use App\Models\Summary;
use App\Models\Translation;
use App\Models\Video;
use App\Services\PromptService;
use App\Services\YoutubeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

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
            'youtube_cookies' => $this->youtubeCookiesStatus(),
        ]);
    }

    public function videos(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);

        return response()->json(
            Video::query()
                ->select([
                    'id',
                    'youtube_id',
                    'url',
                    'title',
                    'status',
                    'is_visible',
                    'error_message',
                    'created_at',
                    'updated_at',
                ])
                ->latest()
                ->paginate($perPage)
        );
    }

    public function uploadYoutubeCookies(Request $request)
    {
        $validated = $request->validate([
            'cookies' => 'required|file|max:2048',
        ]);

        $uploadedFile = $validated['cookies'];
        $contents = file_get_contents($uploadedFile->getRealPath());

        if (!is_string($contents) || trim($contents) === '') {
            return response()->json(['error' => 'Le fichier cookies est vide.'], 422);
        }

        $path = $this->youtubeCookiesPath();
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);
        @chmod($path, 0600);

        return response()->json([
            'message' => 'Cookies YouTube importes.',
            'youtube_cookies' => $this->youtubeCookiesStatus(),
        ]);
    }

    public function deleteYoutubeCookies()
    {
        $path = $this->youtubeCookiesPath();

        if (is_file($path)) {
            File::delete($path);
        }

        return response()->json([
            'message' => 'Cookies YouTube supprimes.',
            'youtube_cookies' => $this->youtubeCookiesStatus(),
        ]);
    }

    public function testYoutubeCookies(Request $request, YoutubeService $youtube)
    {
        $validated = $request->validate([
            'url' => 'nullable|string|max:2048',
        ]);

        $url = $validated['url']
            ?? Video::where('status', 'error')->latest()->value('url')
            ?? Video::latest()->value('url');

        if (!is_string($url) || trim($url) === '') {
            return response()->json(['error' => 'Aucune video disponible pour tester yt-dlp.'], 422);
        }

        return response()->json([
            'url' => $url,
            'diagnostic' => $youtube->diagnoseMetadata($url),
        ]);
    }

    public function prompts(PromptService $prompts)
    {
        return response()->json($prompts->all());
    }

    public function updatePrompt(Request $request, PromptService $prompts, string $key)
    {
        if (!array_key_exists($key, PromptService::DEFAULTS)) {
            return response()->json(['error' => 'Prompt inconnu.'], 404);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:30000',
        ]);

        try {
            return response()->json([
                'message' => 'Prompt enregistre.',
                'prompt' => $prompts->update($key, $validated['content']),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }

    public function resetPrompt(PromptService $prompts, string $key)
    {
        if (!array_key_exists($key, PromptService::DEFAULTS)) {
            return response()->json(['error' => 'Prompt inconnu.'], 404);
        }

        try {
            return response()->json([
                'message' => 'Prompt remis par defaut.',
                'prompt' => $prompts->reset($key),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
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

    public function toggleVisibility(string $id): JsonResponse
    {
        $video = Video::findOrFail($id);
        $video->is_visible = !$video->is_visible;
        $video->save();

        return response()->json([
            'message' => $video->is_visible ? 'Video visible sur l\'accueil' : 'Video masquee de l\'accueil',
            'is_visible' => $video->is_visible,
        ]);
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

    private function youtubeCookiesStatus(): array
    {
        $path = $this->youtubeCookiesPath();

        if (!is_file($path)) {
            return [
                'configured' => false,
                'size' => 0,
                'updated_at' => null,
            ];
        }

        return [
            'configured' => true,
            'size' => filesize($path) ?: 0,
            'updated_at' => date(DATE_ATOM, filemtime($path) ?: time()),
        ];
    }

    private function youtubeCookiesPath(): string
    {
        $path = (string) config('services.youtube.cookies_path', storage_path('app/youtube-cookies.txt'));

        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return base_path($path);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }
}
