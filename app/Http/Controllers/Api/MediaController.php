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
            return response()->json(['error' => $e->getMessage()], 502);
        }

        return response()
            ->download($download['path'], $download['filename'])
            ->deleteFileAfterSend(true);
    }
}
