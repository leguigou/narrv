<?php

namespace Tests\Feature;

use App\Jobs\ProcessYoutubeVideo;
use App\Jobs\GenerateChapterThumbnails;
use App\Models\AdminSession;
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

    public function test_public_video_list_excludes_hidden_videos(): void
    {
        Video::create([
            'youtube_id' => 'dQw4w9WgXcQ',
            'url' => 'https://youtu.be/dQw4w9WgXcQ',
            'status' => 'ready',
            'is_visible' => true,
        ]);

        Video::create([
            'youtube_id' => '9bZkp7q19f0',
            'url' => 'https://youtu.be/9bZkp7q19f0',
            'status' => 'ready',
            'is_visible' => false,
        ]);

        $this->getJson('/api/videos')
            ->assertOk()
            ->assertJsonPath('data.0.youtube_id', 'dQw4w9WgXcQ')
            ->assertJsonMissing(['youtube_id' => '9bZkp7q19f0']);
    }

    public function test_hidden_video_requires_admin_bearer_token(): void
    {
        $token = 'test-admin-token';
        AdminSession::create([
            'token' => hash('sha256', $token),
            'expires_at' => now()->addHour(),
        ]);

        $video = Video::create([
            'youtube_id' => '9bZkp7q19f0',
            'url' => 'https://youtu.be/9bZkp7q19f0',
            'status' => 'ready',
            'is_visible' => false,
        ]);

        $this->getJson("/api/videos/{$video->id}")
            ->assertNotFound();

        $this->getJson("/api/videos/{$video->id}?admin_token={$token}")
            ->assertNotFound();

        $this->withToken($token)
            ->getJson("/api/videos/{$video->id}")
            ->assertOk()
            ->assertJsonPath('youtube_id', '9bZkp7q19f0');
    }

    public function test_admin_can_toggle_video_visibility(): void
    {
        $token = 'test-admin-token';
        AdminSession::create([
            'token' => hash('sha256', $token),
            'expires_at' => now()->addHour(),
        ]);

        $video = Video::create([
            'youtube_id' => '9bZkp7q19f0',
            'url' => 'https://youtu.be/9bZkp7q19f0',
            'status' => 'ready',
            'is_visible' => true,
        ]);

        $this->withToken($token)
            ->putJson("/api/admin/videos/{$video->id}/visibility")
            ->assertOk()
            ->assertJsonPath('is_visible', false);

        $this->assertDatabaseHas('videos', [
            'id' => $video->id,
            'is_visible' => false,
        ]);
    }

    public function test_show_queues_missing_chapter_thumbnails_without_restarting_analysis(): void
    {
        Queue::fake();

        $video = Video::create([
            'youtube_id' => 'dQw4w9WgXcQ',
            'url' => 'https://youtu.be/dQw4w9WgXcQ',
            'status' => 'ready',
            'chapters_json' => [
                ['title' => 'Introduction', 'start_time' => 0, 'end_time' => 30, 'duration' => 30],
            ],
        ]);

        $this->getJson("/api/videos/{$video->id}")
            ->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('chapter_thumbnails_status', 'pending');

        Queue::assertPushed(GenerateChapterThumbnails::class);
        Queue::assertNotPushed(ProcessYoutubeVideo::class);
    }

    public function test_it_serves_a_generated_chapter_thumbnail(): void
    {
        $video = Video::create([
            'youtube_id' => 'dQw4w9WgXcQ',
            'url' => 'https://youtu.be/dQw4w9WgXcQ',
            'status' => 'ready',
            'chapter_thumbnails_status' => 'ready',
            'chapters_json' => [[
                'title' => 'Introduction',
                'start_time' => 0,
                'end_time' => 30,
                'duration' => 30,
                'thumbnail_url' => '/api/videos/1/chapters/0/thumbnail?v=1',
            ]],
        ]);

        $directory = storage_path('app/chapter-thumbnails/' . $video->id);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        file_put_contents($directory . '/000.jpg', 'fake-jpeg');

        try {
            $response = $this->get("/api/videos/{$video->id}/chapters/0/thumbnail");

            $response->assertOk();
            $this->assertStringContainsString('max-age=31536000', $response->headers->get('cache-control'));
            $this->assertStringContainsString('immutable', $response->headers->get('cache-control'));
        } finally {
            @unlink($directory . '/000.jpg');
            @rmdir($directory);
        }
    }
}
