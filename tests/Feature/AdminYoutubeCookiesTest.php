<?php

namespace Tests\Feature;

use App\Models\AdminSession;
use App\Jobs\ProcessYoutubeVideo;
use App\Models\Video;
use App\Services\YoutubeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminYoutubeCookiesTest extends TestCase
{
    use RefreshDatabase;

    private string $token = 'test-admin-token';
    private ?string $cookiesPath = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cookiesPath = storage_path('app/testing-admin-cookies.txt');
        if (!is_dir(dirname($this->cookiesPath))) {
            mkdir(dirname($this->cookiesPath), 0755, true);
        }

        config(['services.youtube.cookies_path' => $this->cookiesPath]);

        AdminSession::create([
            'token' => hash('sha256', $this->token),
            'expires_at' => now()->addHour(),
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->cookiesPath !== null && is_file($this->cookiesPath)) {
            unlink($this->cookiesPath);
        }

        parent::tearDown();
    }

    public function test_admin_can_upload_youtube_cookies_file(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'cookies.txt',
            "# Netscape HTTP Cookie File\n.youtube.com\tTRUE\t/\tTRUE\t1893456000\tSID\tsecret\n"
        );

        $this->withToken($this->token)
            ->post('/api/admin/youtube-cookies', ['cookies' => $file])
            ->assertOk()
            ->assertJsonPath('youtube_cookies.configured', true);

        $this->assertFileExists($this->cookiesPath);
        $this->assertStringContainsString('SID', file_get_contents($this->cookiesPath));
    }

    public function test_admin_stats_include_youtube_cookies_status(): void
    {
        file_put_contents($this->cookiesPath, "# Netscape HTTP Cookie File\n");

        $this->withToken($this->token)
            ->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonPath('youtube_cookies.configured', true);
    }

    public function test_admin_can_delete_youtube_cookies_file(): void
    {
        file_put_contents($this->cookiesPath, "# Netscape HTTP Cookie File\n");

        $this->withToken($this->token)
            ->deleteJson('/api/admin/youtube-cookies')
            ->assertOk()
            ->assertJsonPath('youtube_cookies.configured', false);

        $this->assertFileDoesNotExist($this->cookiesPath);
    }

    public function test_admin_can_list_and_retry_error_videos(): void
    {
        Queue::fake();

        $video = Video::create([
            'youtube_id' => 'XVA5q2H3KKA',
            'url' => 'https://www.youtube.com/watch?v=XVA5q2H3KKA',
            'status' => 'error',
            'error_message' => 'Sign in to confirm you are not a bot.',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/admin/videos')
            ->assertOk()
            ->assertJsonPath('data.0.youtube_id', 'XVA5q2H3KKA')
            ->assertJsonPath('data.0.status', 'error');

        $this->withToken($this->token)
            ->postJson("/api/admin/videos/{$video->id}/retry")
            ->assertOk();

        $this->assertDatabaseHas('videos', [
            'id' => $video->id,
            'status' => 'pending',
            'error_message' => null,
        ]);

        Queue::assertPushed(ProcessYoutubeVideo::class);
    }

    public function test_admin_can_test_youtube_cookies_against_latest_error_video(): void
    {
        Video::create([
            'youtube_id' => 'XVA5q2H3KKA',
            'url' => 'https://www.youtube.com/watch?v=XVA5q2H3KKA',
            'status' => 'error',
            'error_message' => 'Sign in to confirm you are not a bot.',
        ]);

        $this->mock(YoutubeService::class, function ($mock) {
            $mock->shouldReceive('diagnoseMetadata')
                ->once()
                ->with('https://www.youtube.com/watch?v=XVA5q2H3KKA')
                ->andReturn([
                    'ok' => false,
                    'exit_code' => 1,
                    'cookies' => [
                        'exists' => true,
                        'readable' => true,
                        'using_cookies' => true,
                        'size' => 3799,
                    ],
                    'title' => null,
                    'error' => 'Sign in to confirm you are not a bot.',
                    'metadata' => [
                        'ok' => true,
                        'exit_code' => 0,
                        'error' => '',
                    ],
                    'subtitles' => [
                        'ok' => false,
                        'language' => null,
                        'exit_code' => 1,
                        'error' => 'Sign in to confirm you are not a bot.',
                    ],
                ]);
        });

        $this->withToken($this->token)
            ->postJson('/api/admin/youtube-cookies/test')
            ->assertOk()
            ->assertJsonPath('url', 'https://www.youtube.com/watch?v=XVA5q2H3KKA')
            ->assertJsonPath('diagnostic.cookies.using_cookies', true)
            ->assertJsonPath('diagnostic.ok', false)
            ->assertJsonPath('diagnostic.subtitles.ok', false);
    }
}
