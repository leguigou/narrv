<?php

namespace Tests\Unit;

use App\Services\YoutubeService;
use ReflectionClass;
use Tests\TestCase;

class YoutubeServiceTest extends TestCase
{
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
