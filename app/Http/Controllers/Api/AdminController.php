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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

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
        $perPage = min(max((int) $request->query('per_page', 10), 1), 200);

        return response()->json(
            Video::query()
                ->select([
                    'id',
                    'youtube_id',
                    'url',
                    'title',
                    'status',
                    'is_visible',
                    'thumbnail_url',
                    'error_message',
                    'published_at',
                    'created_at',
                    'updated_at',
                ])
                ->latest()
                ->paginate($perPage)
        );
    }

    public function monitoring(): JsonResponse
    {
        $logPath = storage_path('logs/laravel.log');
        $recentLogs = is_file($logPath)
            ? $this->filterLogEntries($this->readLogEntries($logPath), ['levels' => ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY', 'WARNING']])
            : [];

        return response()->json([
            'generated_at' => now()->toAtomString(),
            'app' => [
                'status' => 'ok',
                'environment' => app()->environment(),
                'debug' => (bool) config('app.debug'),
                'url' => config('app.url'),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
            ],
            'database' => $this->databaseStatus(),
            'storage' => $this->storageStatus(),
            'deepseek' => $this->deepseekStatus(),
            'yt_dlp' => $this->commandStatus((string) config('services.youtube.yt_dlp_path', 'yt-dlp'), ['--version'], 'yt-dlp'),
            'ffmpeg' => $this->commandStatus('ffmpeg', ['-version'], 'ffmpeg'),
            'youtube_cookies' => $this->youtubeCookiesStatus(),
            'logs' => [
                'status' => count($recentLogs) > 0 ? 'warning' : 'ok',
                'recent_issues' => count($recentLogs),
                'levels' => $this->countBy($recentLogs, 'level'),
                'sources' => $this->countBy($recentLogs, 'source'),
                'size' => is_file($logPath) ? (filesize($logPath) ?: 0) : 0,
                'updated_at' => is_file($logPath) ? date(DATE_ATOM, filemtime($logPath) ?: time()) : null,
            ],
            'jobs' => [
                'pending_videos' => Video::where('status', 'pending')->count(),
                'processing_videos' => Video::where('status', 'processing')->count(),
                'error_videos' => Video::where('status', 'error')->count(),
            ],
        ]);
    }

    public function logs(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->query('limit', 100), 1), 300);
        $level = strtoupper((string) $request->query('level', ''));
        $source = strtolower((string) $request->query('source', ''));
        $search = trim((string) $request->query('search', ''));
        $path = storage_path('logs/laravel.log');

        if (!is_file($path)) {
            return response()->json([
                'entries' => [],
                'groups' => [],
                'total' => 0,
                'size' => 0,
                'updated_at' => null,
                'levels' => [],
                'sources' => [],
            ]);
        }

        $entries = $this->readLogEntries($path);
        $filtered = $this->filterLogEntries($entries, [
            'levels' => $level && $level !== 'ALL' ? [$level] : ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY', 'WARNING'],
            'source' => $source && $source !== 'all' ? $source : null,
            'search' => $search,
        ]);

        return response()->json([
            'entries' => array_slice(array_reverse($filtered), 0, $limit),
            'groups' => $this->groupLogEntries($filtered),
            'total' => count($filtered),
            'size' => filesize($path) ?: 0,
            'updated_at' => date(DATE_ATOM, filemtime($path) ?: time()),
            'levels' => $this->countBy($filtered, 'level'),
            'sources' => $this->countBy($filtered, 'source'),
        ]);
    }

    public function clearLogs(): JsonResponse
    {
        $path = storage_path('logs/laravel.log');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, '');

        return response()->json(['message' => 'Logs d\'erreur purges.']);
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
        File::deleteDirectory(storage_path('app/chapter-thumbnails'));

        return response()->json(['message' => 'Toutes les donnees ont ete supprimees']);
    }

    public function deleteVideo($id)
    {
        $video = Video::with('transcript')->findOrFail($id);
        $this->deleteTranscriptFile($video);
        File::deleteDirectory(storage_path('app/chapter-thumbnails/' . $video->id));
        $video->delete();

        return response()->json(['message' => 'Video supprimee']);
    }

    public function retryVideo($id)
    {
        $video = Video::with('transcript')->findOrFail($id);

        DB::transaction(function () use ($video): void {
            $this->deleteTranscriptFile($video);
            $video->transcript?->delete();

            $video->update([
                'status' => 'pending',
                'transcript_status' => 'pending',
                'error_message' => null,
                'chapter_thumbnails_status' => null,
            ]);
            File::deleteDirectory(storage_path('app/chapter-thumbnails/' . $video->id));
        });

        ProcessYoutubeVideo::dispatch($video);

        return response()->json(['message' => 'Reanalyse du transcript lancee.']);
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

    private function readLogEntries(string $path): array
    {
        $content = $this->readLogTail($path);

        if (trim($content) === '') {
            return [];
        }

        if (!str_starts_with($content, '[') && preg_match('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/m', $content, $match, PREG_OFFSET_CAPTURE)) {
            $content = substr($content, $match[0][1]);
        }

        $chunks = preg_split('/(?=^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\])/m', $content, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $entries = [];

        foreach ($chunks as $chunk) {
            $raw = trim($chunk);

            if (!preg_match('/^\[(?<date>[^\]]+)\]\s+(?<environment>[^.]+)\.(?<level>[A-Z]+):\s+(?<body>.*)$/s', $raw, $matches)) {
                continue;
            }

            $level = strtoupper($matches['level']);
            $lines = preg_split('/\R/', trim($matches['body'])) ?: [];
            $firstLine = trim($lines[0] ?? '');
            $message = $this->extractLogMessage($firstLine);
            $source = $this->detectLogSource($raw, $message);

            $entries[] = [
                'id' => sha1($raw),
                'date' => $matches['date'],
                'environment' => $matches['environment'],
                'level' => $level,
                'source' => $source,
                'message' => $message,
                'trace' => implode("\n", array_slice($lines, 1)),
                'fingerprint' => sha1($level . '|' . $source . '|' . $this->normalizeLogMessage($message)),
                'raw' => Str::limit($raw, 20000, "\n..."),
            ];
        }

        return $entries;
    }

    private function readLogTail(string $path): string
    {
        $size = filesize($path) ?: 0;
        $maxBytes = 1024 * 1024;

        if ($size <= $maxBytes) {
            return (string) File::get($path);
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return '';
        }

        fseek($handle, -$maxBytes, SEEK_END);
        $content = stream_get_contents($handle);
        fclose($handle);

        return is_string($content) ? $content : '';
    }

    private function extractLogMessage(string $line): string
    {
        $message = preg_replace('/\s+\{(?:\"exception\"|\"userId\"|\"trace\"|\"context\").*$/', '', $line);

        return trim($message ?: $line);
    }

    private function filterLogEntries(array $entries, array $filters): array
    {
        $levels = array_map('strtoupper', $filters['levels'] ?? []);
        $source = $filters['source'] ?? null;
        $search = strtolower((string) ($filters['search'] ?? ''));

        return array_values(array_filter($entries, function (array $entry) use ($levels, $source, $search): bool {
            if ($levels !== [] && !in_array($entry['level'], $levels, true)) {
                return false;
            }

            if ($source && $entry['source'] !== $source) {
                return false;
            }

            if ($search !== '') {
                $haystack = strtolower($entry['message'] . "\n" . $entry['raw']);
                if (!str_contains($haystack, $search)) {
                    return false;
                }
            }

            return true;
        }));
    }

    private function groupLogEntries(array $entries): array
    {
        $groups = [];

        foreach ($entries as $entry) {
            $key = $entry['fingerprint'];

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'id' => $key,
                    'count' => 0,
                    'level' => $entry['level'],
                    'source' => $entry['source'],
                    'message' => $entry['message'],
                    'latest_date' => $entry['date'],
                    'sample' => $entry,
                ];
            }

            $groups[$key]['count']++;
            $groups[$key]['message'] = $entry['message'];
            $groups[$key]['latest_date'] = $entry['date'];
            $groups[$key]['sample'] = $entry;
        }

        usort($groups, fn (array $a, array $b) => strcmp($b['latest_date'], $a['latest_date']));

        return array_values($groups);
    }

    private function detectLogSource(string $raw, string $message): string
    {
        $text = strtolower($raw . ' ' . $message);

        return match (true) {
            str_contains($text, 'deepseek')
                || str_contains($text, 'chat/completions')
                || str_contains($text, 'api key')
                || str_contains($text, 'cle api') => 'deepseek',
            str_contains($text, 'yt-dlp')
                || str_contains($text, 'youtube')
                || str_contains($text, 'subtitle')
                || str_contains($text, 'metadata') => 'youtube',
            str_contains($text, 'sqlite') || str_contains($text, 'database') || str_contains($text, 'sql') => 'database',
            str_contains($text, 'storage') || str_contains($text, 'filesystem') => 'storage',
            default => 'laravel',
        };
    }

    private function normalizeLogMessage(string $message): string
    {
        $message = preg_replace('/\b\d+\b/', '#', $message) ?? $message;
        $message = preg_replace('/\s+/', ' ', $message) ?? $message;

        return strtolower(trim($message));
    }

    private function countBy(array $entries, string $key): array
    {
        $counts = [];

        foreach ($entries as $entry) {
            $value = (string) ($entry[$key] ?? 'unknown');
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    private function databaseStatus(): array
    {
        $startedAt = microtime(true);

        try {
            DB::select('select 1');

            return [
                'status' => 'ok',
                'connection' => config('database.default'),
                'latency_ms' => round((microtime(true) - $startedAt) * 1000, 1),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'connection' => config('database.default'),
                'message' => $e->getMessage(),
            ];
        }
    }

    private function storageStatus(): array
    {
        $path = storage_path();
        $free = disk_free_space($path);
        $total = disk_total_space($path);
        $usedPercent = $free !== false && $total !== false && $total > 0
            ? round((1 - ($free / $total)) * 100, 1)
            : null;

        return [
            'status' => is_writable($path) ? 'ok' : 'error',
            'path' => $path,
            'writable' => is_writable($path),
            'free_bytes' => $free ?: null,
            'total_bytes' => $total ?: null,
            'used_percent' => $usedPercent,
        ];
    }

    private function deepseekStatus(): array
    {
        return [
            'status' => config('services.deepseek.api_key') ? 'ok' : 'warning',
            'configured' => (bool) config('services.deepseek.api_key'),
            'model' => config('services.deepseek.model'),
            'base_url' => config('services.deepseek.base_url'),
            'max_input_characters' => (int) config('services.deepseek.max_input_characters', 45000),
        ];
    }

    private function commandStatus(string $command, array $arguments, string $label): array
    {
        try {
            $process = new Process(array_merge([$command], $arguments));
            $process->setTimeout(8);
            $process->run();
            $output = trim($process->getOutput() ?: $process->getErrorOutput());
            $firstLine = strtok($output, "\n") ?: $output;

            return [
                'status' => $process->isSuccessful() ? 'ok' : 'error',
                'command' => $command,
                'version' => trim($firstLine),
                'exit_code' => $process->getExitCode(),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'command' => $command,
                'version' => null,
                'message' => "{$label} indisponible: {$e->getMessage()}",
            ];
        }
    }
}
