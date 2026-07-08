<?php

namespace Tests\Feature;

use App\Models\Video;
use App\Services\YoutubeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_available_download_formats(): void
    {
        $video = Video::create([
            'youtube_id' => 'dQw4w9WgXcQ',
            'url' => 'https://youtu.be/dQw4w9WgXcQ',
            'status' => 'ready',
        ]);

        $this->mock(YoutubeService::class, function ($mock) use ($video) {
            $mock->shouldReceive('downloadFormats')
                ->once()
                ->withArgs(fn ($arg) => $arg instanceof Video && $arg->id === $video->id)
                ->andReturn([
                    'title' => 'Test video',
                    'video' => [
                        ['format_id' => '137', 'label' => '1080p - mp4 + audio'],
                    ],
                    'audio' => [
                        ['format_id' => '140', 'label' => '128 kbps - m4a'],
                    ],
                    'defaults' => [
                        'video' => 'best',
                        'audio' => 'bestaudio',
                    ],
                ]);
        });

        $this->getJson("/api/videos/{$video->id}/formats")
            ->assertOk()
            ->assertJsonPath('video.0.format_id', '137')
            ->assertJsonPath('audio.0.format_id', '140')
            ->assertJsonPath('defaults.video', 'best');
    }
}
