<?php

namespace Tests\Feature;

use App\Models\Transcript;
use App\Models\Video;
use App\Services\DeepseekService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranslationStreamingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_streams_each_translated_chunk_then_persists_the_complete_translation(): void
    {
        $video = Video::create([
            'youtube_id' => 'dQw4w9WgXcQ',
            'url' => 'https://youtu.be/dQw4w9WgXcQ',
            'status' => 'ready',
        ]);

        $transcript = Transcript::create([
            'video_id' => $video->id,
            'full_text' => 'Premier bloc. Deuxieme bloc.',
            'language' => 'fr',
            'word_count' => 4,
            'segments_json' => [],
        ]);

        $this->mock(DeepseekService::class, function ($mock): void {
            $mock->model = 'test-model';
            $mock->shouldReceive('translationChunkCount')->once()->andReturn(2);
            $mock->shouldReceive('translate')
                ->once()
                ->andReturnUsing(function ($text, $target, $source, $onChunk): string {
                    $onChunk('First block.', 1, 2);
                    $onChunk('Second block.', 2, 2);

                    return "First block.\n\nSecond block.";
                });
        });

        $response = $this->json(
            'POST',
            "/api/videos/{$video->id}/translate",
            ['language' => 'en'],
            ['Accept' => 'application/json, application/x-ndjson']
        );

        $response->assertOk();
        $events = collect(explode("\n", trim($response->streamedContent())))
            ->map(fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR));

        $this->assertSame(['start', 'chunk', 'chunk', 'complete'], $events->pluck('type')->all());
        $this->assertSame(2, $events[0]['total']);
        $this->assertSame('First block.', $events[1]['content']);
        $this->assertSame(2, $events[2]['index']);
        $this->assertSame("First block.\n\nSecond block.", $events[3]['translation']['content']);
        $this->assertDatabaseHas('translations', [
            'transcript_id' => $transcript->id,
            'target_language' => 'en',
            'content' => "First block.\n\nSecond block.",
        ]);
    }
}
