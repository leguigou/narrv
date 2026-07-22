<?php

namespace Tests\Unit;

use App\Services\DeepseekService;
use App\Services\PromptService;
use Tests\TestCase;

class DeepseekServiceTest extends TestCase
{
    public function test_it_translates_long_transcripts_in_natural_chunks_without_losing_content(): void
    {
        config([
            'services.deepseek.api_key' => 'test-key',
            'services.deepseek.translation_chunk_characters' => 30,
            'services.deepseek.max_input_characters' => 100,
        ]);

        $prompts = $this->mock(PromptService::class);
        $prompts->shouldReceive('render')
            ->with('translate_system', \Mockery::on(function (array $variables): bool {
                return $variables['source_language'] === 'French'
                    && $variables['target_language'] === 'English';
            }))
            ->andReturnUsing(fn (string $key, array $variables): string => $variables['transcript']);

        $service = new class($prompts) extends DeepseekService
        {
            public array $chunks = [];

            protected function callApi(array $messages, float $temperature = 0.3): ?string
            {
                $this->chunks[] = $messages[0]['content'];

                return strtoupper($messages[0]['content']);
            }
        };

        $text = "Premier paragraphe.\n\nDeuxieme paragraphe assez long.\n\nTroisieme paragraphe.";
        $progress = [];
        $translated = $service->translate(
            $text,
            'en',
            'fr',
            function (string $content, int $index, int $total) use (&$progress): void {
                $progress[] = compact('content', 'index', 'total');
            }
        );

        $this->assertGreaterThan(1, count($service->chunks));
        $this->assertSame(
            preg_replace('/\s+/', ' ', $text),
            preg_replace('/\s+/', ' ', implode(' ', $service->chunks))
        );
        $this->assertTrue(collect($service->chunks)->every(fn (string $chunk): bool => mb_strlen($chunk) <= 30));
        $this->assertSame(implode("\n\n", array_map('strtoupper', $service->chunks)), $translated);
        $this->assertCount(count($service->chunks), $progress);
        $this->assertSame(count($service->chunks), $progress[array_key_last($progress)]['index']);
        $this->assertSame(count($service->chunks), $progress[array_key_last($progress)]['total']);
    }

    public function test_it_keeps_short_transcripts_in_one_request(): void
    {
        config([
            'services.deepseek.api_key' => 'test-key',
            'services.deepseek.translation_chunk_characters' => 12000,
        ]);

        $prompts = $this->mock(PromptService::class);
        $prompts->shouldReceive('render')
            ->once()
            ->andReturnUsing(fn (string $key, array $variables): string => $variables['transcript']);

        $service = new class($prompts) extends DeepseekService
        {
            public int $calls = 0;

            protected function callApi(array $messages, float $temperature = 0.3): ?string
            {
                $this->calls++;

                return 'Translated text';
            }
        };

        $this->assertSame('Translated text', $service->translate('Texte court.', 'en', 'fr'));
        $this->assertSame(1, $service->calls);
    }
}
