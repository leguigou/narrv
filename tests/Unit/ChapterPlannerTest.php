<?php

namespace Tests\Unit;

use App\Services\ChapterPlanner;
use App\Services\DeepseekService;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ChapterPlannerTest extends TestCase
{
    private function planner(DeepseekService $ai): ChapterPlanner
    {
        return new ChapterPlanner($ai);
    }

    public function test_it_builds_a_timestamped_outline_covering_the_whole_video(): void
    {
        config(['services.deepseek.max_input_characters' => 45000]);

        $segments = [];
        for ($index = 0; $index < 12; $index++) {
            $segments[] = [
                'start' => $index * 10,
                'end' => $index * 10 + 10,
                'text' => "Segment numero {$index}.",
            ];
        }

        $outline = $this->planner(Mockery::mock(DeepseekService::class))->outline($segments);
        $lines = explode("\n", $outline);

        // Blocs de 20 secondes : deux segments par ligne.
        $this->assertCount(6, $lines);
        $this->assertStringStartsWith('[0:00] Segment numero 0.', $lines[0]);
        $this->assertStringStartsWith('[0:20] Segment numero 2.', $lines[1]);
        $this->assertStringContainsString('Segment numero 11.', $lines[5]);
        $this->assertStringNotContainsString('  ', $outline);
    }

    public function test_it_compacts_the_outline_instead_of_truncating_the_transcript(): void
    {
        config(['services.deepseek.max_input_characters' => 2000]);

        $segments = [];
        for ($index = 0; $index < 600; $index++) {
            $segments[] = [
                'start' => $index * 10,
                'end' => $index * 10 + 10,
                'text' => str_repeat('bla ', 12),
            ];
        }

        $outline = $this->planner(Mockery::mock(DeepseekService::class))->outline($segments);

        // 6000 secondes de vidéo pour 2000 caractères de budget : les blocs sont
        // élargis et leur texte raccourci, sans perdre la fin de la vidéo.
        $this->assertLessThanOrEqual(2000, mb_strlen($outline));
        $this->assertStringStartsWith('[0:00]', $outline);

        preg_match_all('/\[(?:(\d+):)?(\d+):(\d{2})\]/', $outline, $matches, PREG_SET_ORDER);
        $this->assertGreaterThanOrEqual(3, count($matches), 'La vidéo doit rester découpée en plusieurs blocs.');

        $last = end($matches);
        $lastSeconds = ((int) ($last[1] ?? 0)) * 3600 + ((int) $last[2]) * 60 + (int) $last[3];
        $this->assertGreaterThanOrEqual(4800, $lastSeconds, 'Le dernier bloc doit couvrir la fin de la vidéo.');
    }

    public function test_it_adds_end_times_so_chapters_cover_the_whole_video(): void
    {
        $chapters = $this->planner(Mockery::mock(DeepseekService::class))->withDurations([
            ['title' => 'Introduction', 'start_time' => 0.0],
            ['title' => 'Deuxieme partie', 'start_time' => 120.0],
        ], 300.0);

        $this->assertSame([0.0, 120.0], array_column($chapters, 'start_time'));
        $this->assertSame([120.0, 300.0], array_column($chapters, 'end_time'));
        $this->assertSame([120.0, 180.0], array_column($chapters, 'duration'));
    }

    public function test_it_refuses_a_transcript_without_text(): void
    {
        $ai = Mockery::mock(DeepseekService::class);
        $ai->shouldNotReceive('generateChapters');

        $this->expectException(RuntimeException::class);

        $this->planner($ai)->plan([], 120.0);
    }

    public function test_the_chapter_budget_follows_the_video_length(): void
    {
        // Une heure de video : 12 a 14 chapitres (un toutes les 4 a 5 minutes).
        $this->assertSame(['min' => 12, 'max' => 14], DeepseekService::chapterBudget(3600.0));
        $this->assertSame(['min' => 6, 'max' => 7], DeepseekService::chapterBudget(1680.0));
        $this->assertSame(['min' => 2, 'max' => 3], DeepseekService::chapterBudget(600.0));
        $this->assertSame(['min' => 0, 'max' => 0], DeepseekService::chapterBudget(0.0));
    }

    public function test_it_never_keeps_more_chapters_than_the_budget(): void
    {
        // 10 minutes de video : 3 chapitres maximum, meme si le modele en propose 12.
        $ai = Mockery::mock(DeepseekService::class);
        $ai->shouldReceive('generateChapters')->once()->andReturn(
            collect(range(0, 11))
                ->map(fn (int $index): array => ['title' => "Partie {$index}", 'start_time' => (float) ($index * 50)])
                ->all()
        );

        $chapters = $this->planner($ai)->plan([
            ['start' => 0, 'end' => 10, 'text' => 'Bonjour tout le monde.'],
        ], 600.0);

        $this->assertSame(['Partie 0', 'Partie 4', 'Partie 8'], array_column($chapters, 'title'));
        $this->assertSame([0.0, 200.0, 400.0], array_column($chapters, 'start_time'));
        $this->assertSame([200.0, 400.0, 600.0], array_column($chapters, 'end_time'));
    }

    public function test_it_sends_the_outline_to_the_model_and_keeps_its_chapters(): void
    {
        $ai = Mockery::mock(DeepseekService::class);
        $ai->shouldReceive('generateChapters')
            ->once()
            ->withArgs(function (string $outline, float $duration, ?string $title = null): bool {
                $this->assertStringContainsString('[0:00] Bonjour tout le monde.', $outline);
                $this->assertSame(600.0, $duration);
                $this->assertSame('Wan 3.0 vs Seedance 2.5', $title);

                return true;
            })
            ->andReturn([
                ['title' => 'Introduction', 'start_time' => 0.0],
                ['title' => 'Le sujet principal', 'start_time' => 300.0],
            ]);

        $chapters = $this->planner($ai)->plan([
            ['start' => 0, 'end' => 10, 'text' => 'Bonjour tout le monde.'],
            ['start' => 10, 'end' => 20, 'text' => 'On commence.'],
        ], 600.0, 'Wan 3.0 vs Seedance 2.5');

        $this->assertSame(['Introduction', 'Le sujet principal'], array_column($chapters, 'title'));
        $this->assertSame([0.0, 300.0], array_column($chapters, 'start_time'));
        $this->assertSame([300.0, 600.0], array_column($chapters, 'end_time'));
    }
}
