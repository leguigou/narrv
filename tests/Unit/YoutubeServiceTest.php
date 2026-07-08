<?php

namespace Tests\Unit;

use App\Services\YoutubeService;
use ReflectionClass;
use Tests\TestCase;

class YoutubeServiceTest extends TestCase
{
    private ?string $cookiesFile = null;

    protected function tearDown(): void
    {
        if ($this->cookiesFile !== null && is_file($this->cookiesFile)) {
            unlink($this->cookiesFile);
        }

        parent::tearDown();
    }

    public function test_it_adds_cookie_arguments_when_cookie_file_exists(): void
    {
        $this->cookiesFile = storage_path('app/testing-youtube-cookies.txt');
        if (!is_dir(dirname($this->cookiesFile))) {
            mkdir(dirname($this->cookiesFile), 0755, true);
        }

        file_put_contents($this->cookiesFile, "# Netscape HTTP Cookie File\n");
        config([
            'services.youtube.cookies_path' => $this->cookiesFile,
            'services.youtube.sleep_requests' => 2,
            'services.youtube.retries' => 7,
            'services.youtube.retry_sleep' => 'http:exp=2:30',
        ]);

        $service = new YoutubeService();
        $method = (new ReflectionClass($service))->getMethod('ytDlpCommand');
        $method->setAccessible(true);

        $command = $method->invoke($service, ['--dump-json', 'https://youtu.be/dQw4w9WgXcQ']);

        $this->assertContains('--cookies', $command);
        $this->assertContains($this->cookiesFile, $command);
        $this->assertContains('--sleep-requests', $command);
        $this->assertContains('2', $command);
        $this->assertContains('--retries', $command);
        $this->assertContains('7', $command);
        $this->assertContains('--retry-sleep', $command);
        $this->assertContains('http:exp=2:30', $command);
    }

    public function test_it_parses_vtt_timestamps_with_and_without_hours(): void
    {
        $service = new YoutubeService();
        $method = (new ReflectionClass($service))->getMethod('vttTimeToSeconds');
        $method->setAccessible(true);

        $this->assertEqualsWithDelta(1.234, $method->invoke($service, '00:01.234'), 0.001);
        $this->assertEqualsWithDelta(62.345, $method->invoke($service, '00:01:02.345'), 0.001);
    }

    public function test_it_cleans_vtt_cues_into_segments(): void
    {
        $service = new YoutubeService();
        $method = (new ReflectionClass($service))->getMethod('parseVtt');
        $method->setAccessible(true);

        $segments = $method->invoke($service, <<<VTT
WEBVTT

00:00.000 --> 00:01.000 align:start position:0%
<c>Hello</c> &amp; welcome

00:01.000 --> 00:02.500
to Narrv
VTT);

        $this->assertCount(2, $segments);
        $this->assertSame('Hello & welcome', $segments[0]['text']);
        $this->assertEqualsWithDelta(2.5, $segments[1]['end'], 0.001);
    }
}
