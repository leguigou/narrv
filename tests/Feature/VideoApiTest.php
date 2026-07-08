<?php

namespace Tests\Feature;

use App\Jobs\ProcessYoutubeVideo;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VideoApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_non_youtube_urls(): void
    {
        $this->postJson('/api/videos', ['url' => 'https://example.com/watch?v=dQw4w9WgXcQ'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'URL YouTube invalide');
    }

    public function test_it_creates_video_and_dispatches_processing_job(): void
    {
        Queue::fake();

        $this->postJson('/api/videos', [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'preferred_language' => 'fr-FR',
        ])
            ->assertCreated()
            ->assertJsonPath('youtube_id', 'dQw4w9WgXcQ')
            ->assertJsonPath('youtube_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
            ->assertJsonPath('already_imported', false)
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('language', 'fr');

        $this->assertDatabaseHas('videos', [
            'youtube_id' => 'dQw4w9WgXcQ',
            'language' => 'fr',
            'status' => 'pending',
        ]);

        Queue::assertPushed(ProcessYoutubeVideo::class);
    }

    public function test_it_accepts_common_youtube_url_variants(): void
    {
        Queue::fake();

        $urls = [
            'https://youtu.be/dQw4w9WgXcQ?si=abc',
            'https://www.youtube.com/shorts/9bZkp7q19f0',
            'https://www.youtube.com/embed/3JZ_D3ELwOQ',
            'https://m.youtube.com/live/L_jWHffIx5E',
            'https://www.youtube-nocookie.com/embed/oHg5SJYRHA0',
        ];

        foreach ($urls as $url) {
            $this->postJson('/api/videos', ['url' => $url])->assertStatus(201);
        }

        $this->assertDatabaseHas('videos', ['youtube_id' => 'dQw4w9WgXcQ']);
        $this->assertDatabaseHas('videos', ['youtube_id' => '9bZkp7q19f0']);
        $this->assertDatabaseHas('videos', ['youtube_id' => '3JZ_D3ELwOQ']);
        $this->assertDatabaseHas('videos', ['youtube_id' => 'L_jWHffIx5E']);
        $this->assertDatabaseHas('videos', ['youtube_id' => 'oHg5SJYRHA0']);
    }

    public function test_it_returns_existing_video_without_dispatching_again(): void
    {
        Queue::fake();

        Video::create([
            'youtube_id' => 'dQw4w9WgXcQ',
            'url' => 'https://youtu.be/dQw4w9WgXcQ',
            'status' => 'ready',
        ]);

        $this->postJson('/api/videos', ['url' => 'youtu.be/dQw4w9WgXcQ'])
            ->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('youtube_id', 'dQw4w9WgXcQ')
            ->assertJsonPath('youtube_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
            ->assertJsonPath('already_imported', true);

        Queue::assertNothingPushed();
    }
}
