<?php

namespace Tests\Unit;

use App\Services\DeepseekService;
use App\Services\PromptService;
use RuntimeException;
use Tests\TestCase;

class DeepseekChapterGenerationTest extends TestCase
{
    /**
     * @param  callable(): string  $response
     */
    private function serviceReturning(callable $response): DeepseekService
    {
        config(['services.deepseek.api_key' => 'test-key']);

        $prompts = $this->mock(PromptService::class);
        $prompts->shouldReceive('render')
            ->with('chapters_system', \Mockery::type('array'))
            ->andReturnUsing(fn (string $key, array $variables): string => $variables['transcript']);

        return new class($prompts, $response) extends DeepseekService
        {
            /** @var callable(): string */
            private $response;

            public array $prompts = [];

            public function __construct(PromptService $prompts, callable $response)
            {
                parent::__construct($prompts);
                $this->response = $response;
            }

            protected function callApi(array $messages, float $temperature = 0.3): ?string
            {
                $this->prompts[] = $messages[0]['content'];

                return ($this->response)();
            }
        };
    }

    public function test_it_reads_a_plain_json_answer(): void
    {
        $service = $this->serviceReturning(fn (): string => '[{"title":"Introduction","start_time":0},{"title":"Le sujet","start_time":312}]');

        $chapters = $service->generateChapters('[0:00] bla', 600.0);

        $this->assertSame(['Introduction', 'Le sujet'], array_column($chapters, 'title'));
        $this->assertSame([0.0, 312.0], array_column($chapters, 'start_time'));
    }

    public function test_it_reads_a_fenced_or_wrapped_json_answer(): void
    {
        $fenced = $this->serviceReturning(fn (): string => "```json\n[{\"title\":\"Intro\",\"start_time\":0}]\n```");
        $wrapped = $this->serviceReturning(fn (): string => "Voici les chapitres :\n[{\"title\":\"Intro\",\"start_time\":0}]\nVoila.");

        $this->assertSame('Intro', $fenced->generateChapters('[0:00] bla', 100.0)[0]['title']);
        $this->assertSame('Intro', $wrapped->generateChapters('[0:00] bla', 100.0)[0]['title']);
    }

    public function test_it_sorts_deduplicates_and_clamps_the_chapters(): void
    {
        $service = $this->serviceReturning(fn (): string => json_encode([
            ['title' => 'Troisieme', 'start_time' => 900],
            ['title' => 'Premiere', 'start_time' => 0],
            ['title' => 'Doublon', 'start_time' => 0],
            ['title' => '   ', 'start_time' => 120],
            ['title' => 'Deuxieme', 'start_time' => 300.5],
            ['title' => 'Hors video', 'start_time' => 6000],
        ]));

        $chapters = $service->generateChapters('[0:00] bla', 1000.0);

        $this->assertSame(['Premiere', 'Deuxieme', 'Troisieme', 'Hors video'], array_column($chapters, 'title'));
        $this->assertSame([0.0, 300.5, 900.0, 1000.0], array_column($chapters, 'start_time'));
    }

    public function test_it_fails_when_the_model_returns_nothing_usable(): void
    {
        $this->expectException(RuntimeException::class);

        $this->serviceReturning(fn (): string => 'Je ne peux pas découper cette vidéo.')
            ->generateChapters('[0:00] bla', 100.0);
    }
}
