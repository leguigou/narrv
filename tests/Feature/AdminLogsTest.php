<?php

namespace Tests\Feature;

use App\Models\AdminSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLogsTest extends TestCase
{
    use RefreshDatabase;

    private string $token = 'test-admin-token';
    private string $logPath;
    private ?string $originalLog = null;
    private bool $hadOriginalLog = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logPath = storage_path('logs/laravel.log');
        $this->hadOriginalLog = is_file($this->logPath);
        $this->originalLog = $this->hadOriginalLog ? file_get_contents($this->logPath) : null;

        if (!is_dir(dirname($this->logPath))) {
            mkdir(dirname($this->logPath), 0755, true);
        }

        AdminSession::create([
            'token' => hash('sha256', $this->token),
            'expires_at' => now()->addHour(),
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->hadOriginalLog) {
            file_put_contents($this->logPath, $this->originalLog ?? '');
        } elseif (is_file($this->logPath)) {
            unlink($this->logPath);
        }

        parent::tearDown();
    }

    public function test_admin_can_list_error_logs(): void
    {
        file_put_contents($this->logPath, implode("\n", [
            '[2026-07-10 08:00:00] production.INFO: Health check OK',
            '[2026-07-10 08:01:00] production.ERROR: Unable to download subtitles {"exception":"RuntimeException"}',
            '#0 /var/www/app/Services/YoutubeService.php(42): throw',
            '[2026-07-10 08:02:00] production.CRITICAL: Database is locked',
        ]));

        $this->withToken($this->token)
            ->getJson('/api/admin/logs')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('entries.0.level', 'CRITICAL')
            ->assertJsonPath('entries.1.level', 'ERROR')
            ->assertJsonPath('entries.1.message', 'Unable to download subtitles');
    }

    public function test_admin_can_filter_and_group_logs(): void
    {
        file_put_contents($this->logPath, implode("\n", [
            '[2026-07-10 08:01:00] production.ERROR: DeepSeek API returned HTTP 401',
            '[2026-07-10 08:02:00] production.ERROR: DeepSeek API returned HTTP 429',
            '[2026-07-10 08:03:00] production.WARNING: yt-dlp rate limited YouTube',
            '[2026-07-10 08:04:00] production.ERROR: Database is locked',
        ]));

        $this->withToken($this->token)
            ->getJson('/api/admin/logs?level=ERROR&source=deepseek&search=HTTP')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('entries.0.source', 'deepseek')
            ->assertJsonPath('groups.0.count', 2)
            ->assertJsonPath('groups.0.message', 'DeepSeek API returned HTTP 429');
    }

    public function test_admin_can_clear_error_logs(): void
    {
        file_put_contents($this->logPath, '[2026-07-10 08:01:00] production.ERROR: Boom');

        $this->withToken($this->token)
            ->deleteJson('/api/admin/logs')
            ->assertOk()
            ->assertJsonPath('message', 'Logs d\'erreur purges.');

        $this->assertSame('', file_get_contents($this->logPath));
    }
}
