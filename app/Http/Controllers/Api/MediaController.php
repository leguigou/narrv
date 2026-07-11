<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Services\YoutubeService;
use Illuminate\Http\Request;
use RuntimeException;

class MediaController extends Controller
{
    public function formats($id, YoutubeService $youtube)
    {
        $video = Video::findOrFail($id);

        try {
            return response()->json($youtube->downloadFormats($video));
        } catch (RuntimeException $e) {
            logger()->error('YouTube media formats retrieval failed', [
                'source' => 'youtube',
                'video_id' => $video->id,
                'youtube_id' => $video->youtube_id,
                'url' => $video->url,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    public function download(Request $request, $id, YoutubeService $youtube)
    {
        set_time_limit(0);

        $validated = $request->validate([
            'type' => 'required|string|in:video,audio',
            'format_id' => 'required|string|max:64',
        ]);

        $video = Video::findOrFail($id);

        try {
            $download = $youtube->downloadMedia($video, $validated['type'], $validated['format_id']);
        } catch (RuntimeException $e) {
            logger()->error('YouTube media download failed', [
                'source' => 'youtube',
                'video_id' => $video->id,
                'youtube_id' => $video->youtube_id,
                'url' => $video->url,
                'type' => $validated['type'],
                'format_id' => $validated['format_id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => $e->getMessage()], 502);
        }

        return response()
            ->download($download['path'], $download['filename'])
            ->deleteFileAfterSend(true);
    }
}
