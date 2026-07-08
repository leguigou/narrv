<?php

namespace Tests\Feature;

use App\Models\AdminSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
}
