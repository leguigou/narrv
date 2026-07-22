<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateChapterThumbnails;
use App\Jobs\ProcessYoutubeVideo;
use App\Models\AdminSession;
use App\Models\Translation;
use App\Models\Video;
use Illuminate\Http\Request;
use Throwable;

class VideoController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'url' => 'required|string|max:2048',
            'preferred_language' => 'nullable|string|max:32',
        ]);

        $url = $this->normalizeUrl($validated['url']);
        $youtubeId = $this->extractYoutubeId($url);
        $preferredLanguage = $this->normalizeLanguage($validated['preferred_language'] ?? null) ?? 'en';

        if ($youtubeId === null) {
            return response()->json(['error' => 'URL YouTube invalide'], 422);
        }

        $video = Video::firstOrCreate(
            ['youtube_id' => $youtubeId],
            ['url' => $url, 'language' => $preferredLanguage, 'status' => 'pending']
        );

        $alreadyImported = !$video->wasRecentlyCreated;

        if ($alreadyImported && $video->status !== 'ready') {
            $video->update(['language' => $preferredLanguage]);
        }

        if ($video->wasRecentlyCreated) {
            ProcessYoutubeVideo::dispatch($video);
        }

        return response()->json(
            $video->setAttribute('already_imported', $alreadyImported),
            $video->wasRecentlyCreated ? 201 : 200
        );
    }

    public function index(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);

        return response()->json(Video::where('is_visible', true)->latest()->paginate($perPage));
    }

    public function search(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 50);
        $query = trim((string) $request->query('q', ''));

        $videos = Video::where('is_visible', true);

        if ($query !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $query) . '%';

            $videos = $videos->where(function ($q) use ($like) {
                $q->where('title', 'LIKE', $like)
                  ->orWhere('channel_name', 'LIKE', $like)
                  ->orWhere('youtube_id', 'LIKE', $like)
                  ->orWhereHas('transcript', function ($t) use ($like) {
                      $t->where('full_text', 'LIKE', $like);
                  })
                  ->orWhereHas('transcript.translations', function ($tr) use ($like) {
                      $tr->where('content', 'LIKE', $like);
                  });
            });
        }

        return response()->json(
            $videos->with('transcript')
                ->latest()
                ->paginate($perPage)
                ->through(function ($video) {
                    $data = $video->toArray();
                    $data['has_transcript'] = $video->transcript !== null;
                    unset($data['transcript']);
                    return $data;
                })
        );
    }

    public function show(Request $request, $id)
    {
        $video = Video::with('transcript')->findOrFail($id);

        if (!$video->is_visible) {
            $adminToken = $request->bearerToken();
            if ($adminToken !== null && $this->isValidAdminToken($adminToken)) {
                return response()->json($video);
            }

            return response()->json(['error' => 'Cette video n\'est pas disponible'], 404);
        }

        $this->ensureChapterThumbnailsQueued($video);

        return response()->json($video->fresh('transcript'));
    }

    public function chapterThumbnail(int $id, int $chapter)
    {
        $video = Video::where('is_visible', true)->findOrFail($id);
        $chapters = is_array($video->chapters_json) ? $video->chapters_json : [];

        if (!isset($chapters[$chapter]['thumbnail_url'])) {
            abort(404);
        }

        $path = storage_path('app/chapter-thumbnails/' . $video->id . '/' . sprintf('%03d.jpg', $chapter));
        if (!is_file($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    private function ensureChapterThumbnailsQueued(Video $video): void
    {
        if ($video->status !== 'ready' || empty($video->chapters_json)) {
            return;
        }

        $status = $video->chapter_thumbnails_status;
        $isNew = $status === null;
        $isStalePending = $status === 'pending' && $video->updated_at?->lt(now()->subMinutes(2));
        $isStaleProcessing = $status === 'processing' && $video->updated_at?->lt(now()->subMinutes(25));

        if (!$isNew && !$isStalePending && !$isStaleProcessing) {
            return;
        }

        $query = Video::whereKey($video->id);
        $status === null
            ? $query->whereNull('chapter_thumbnails_status')
            : $query->where('chapter_thumbnails_status', $status);

        $claimed = $query->update([
            'chapter_thumbnails_status' => 'pending',
            'updated_at' => now(),
        ]);

        if ($claimed === 0) {
            return;
        }

        $video->chapter_thumbnails_status = 'pending';

        try {
            // Respecte la connexion par défaut du déploiement (database, Redis,
            // etc.) afin que le worker actif consomme réellement ce job.
            GenerateChapterThumbnails::dispatch($video);
        } catch (Throwable $e) {
            $video->update(['chapter_thumbnails_status' => 'error']);
            logger()->warning('Unable to queue legacy chapter thumbnails', [
                'source' => 'youtube',
                'video_id' => $video->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function isValidAdminToken(string $token): bool
    {
        return AdminSession::where('token', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->exists();
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if (!preg_match('/^https?:\/\//i', $url)) {
            $url = 'https://' . $url;
        }

        return $url;
    }

    private function extractYoutubeId(string $url): ?string
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        $path = trim($parts['path'] ?? '', '/');

        if ($host === 'youtu.be') {
            return $this->validYoutubeId(explode('/', $path)[0] ?? null);
        }

        $isYoutubeHost = $host === 'youtube.com' || str_ends_with($host, '.youtube.com');
        $isYoutubeNoCookieHost = $host === 'youtube-nocookie.com' || str_ends_with($host, '.youtube-nocookie.com');

        if (!$isYoutubeHost && !$isYoutubeNoCookieHost) {
            return null;
        }

        parse_str($parts['query'] ?? '', $query);
        if (isset($query['v'])) {
            return $this->validYoutubeId($query['v']);
        }

        $segments = explode('/', $path);
        if (in_array($segments[0] ?? '', ['embed', 'shorts', 'v', 'live'], true)) {
            return $this->validYoutubeId($segments[1] ?? null);
        }

        return null;
    }

    private function validYoutubeId(?string $value): ?string
    {
        return is_string($value) && preg_match('/^[a-zA-Z0-9_-]{11}$/', $value) ? $value : null;
    }

    private function normalizeLanguage(?string $language): ?string
    {
        if (!is_string($language) || trim($language) === '') {
            return null;
        }

        $language = strtolower(str_replace('_', '-', trim($language)));
        $language = explode(',', $language)[0];

        if (!preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})?$/', $language)) {
            return null;
        }

        return explode('-', $language)[0];
    }
}
