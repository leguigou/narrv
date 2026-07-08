<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Jobs\ProcessYoutubeVideo;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    public function store(Request $request)
    {
        $request->validate(['url' => 'required|string']);

        // Extrait youtube_id
        preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|v\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $request->url, $matches);

        if (empty($matches[1])) {
            return response()->json(['error' => 'URL YouTube invalide'], 422);
        }

        $youtubeId = $matches[1];

        // Vérifie si déjà en base
        $video = Video::firstOrCreate(
            ['youtube_id' => $youtubeId],
            ['url' => $request->url, 'status' => 'pending']
        );

        if ($video->wasRecentlyCreated) {
            ProcessYoutubeVideo::dispatch($video);
        }

        return response()->json($video, 201);
    }

    public function index()
    {
        $videos = Video::latest()->paginate(10);
        return response()->json($videos);
    }

    public function show($id)
    {
        $video = Video::with('transcript')->findOrFail($id);
        return response()->json($video);
    }
}
