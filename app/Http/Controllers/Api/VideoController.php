<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessYoutubeVideo;
use App\Models\Video;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'url' => 'required|string|max:2048',
        ]);

        $url = $this->normalizeUrl($validated['url']);
        $youtubeId = $this->extractYoutubeId($url);

        if ($youtubeId === null) {
            return response()->json(['error' => 'URL YouTube invalide'], 422);
        }

        $video = Video::firstOrCreate(
            ['youtube_id' => $youtubeId],
            ['url' => $url, 'status' => 'pending']
        );

        if ($video->wasRecentlyCreated) {
            ProcessYoutubeVideo::dispatch($video);
        }

        return response()->json($video, $video->wasRecentlyCreated ? 201 : 200);
    }

    public function index(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);

        return response()->json(Video::latest()->paginate($perPage));
    }

    public function show($id)
    {
        return response()->json(Video::with('transcript')->findOrFail($id));
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
}
