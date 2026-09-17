<?php

namespace Tests\Feature;

use App\Models\AdminSession;
use App\Models\Transcript;
use App\Models\Video;
use App\Services\ChapterGenerationLauncher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChapterGenerationApiTest extends TestCase
{
    use RefreshDatabase;

    private function video(array $attributes = []): Video
    {
        return Video::create(array_merge([
            'youtube_id' => 'dQw4w9WgXcQ',
            'url' => 'https://youtu.be/dQw4w9WgXcQ',
            'title' => 'Une video sans chapitres',
            'duration' => 600,
            'status' => 'ready',
            'transcript_status' => 'ready',
            'is_visible' => true,
        ], $attributes));
    }

    private function addTranscript(Video $video): void
    {
        Transcript::create([
            'video_id' => $video->id,
            'raw_file_path' => 'transcripts/dQw4w9WgXcQ.fr.vtt',
            'full_text' => 'Bonjour tout le monde.',
            'language' => 'fr',
            'word_count' => 4,
            'segments_json' => [['start' => 0, 'end' => 10, 'text' => 'Bonjour tout le monde.']],
        ]);
    }

    public function test_it_starts_the_generation_for_a_video_without_chapters(): void
    {
        $video = $this->video();
        $this->addTranscript($video);

        $launcher = $this->mock(ChapterGenerationLauncher::class);
        $launcher->shouldReceive('launch')->once()->andReturn(true);

        $this->postJson("/api/videos/{$video->id}/chapters/generate")
            ->assertStatus(202)
            ->assertJsonPath('started', true);
    }

    public function test_it_reports_an_already_running_generation_without_restarting_it(): void
    {
        $video = $this->video(['chapters_status' => 'processing']);
        $this->addTranscript($video);

        $launcher = $this->mock(ChapterGenerationLauncher::class);
        $launcher->shouldReceive('launch')->once()->andReturn(false);

        $this->postJson("/api/videos/{$video->id}/chapters/generate")
            ->assertStatus(202)
            ->assertJsonPath('started', false)
            ->assertJsonPath('chapters_status', 'processing');
    }

    public function test_it_refuses_a_video_without_transcript(): void
    {
        $video = $this->video(['transcript_status' => 'unavailable']);

        $launcher = $this->mock(ChapterGenerationLauncher::class);
        $launcher->shouldNotReceive('launch');

        $this->postJson("/api/videos/{$video->id}/chapters/generate")
            ->assertStatus(422)
            ->assertJsonPath('error', 'Aucun transcript disponible pour cette video.');
    }

    public function test_it_does_not_regenerate_the_chapters_of_a_video_that_already_has_some(): void
    {
        $video = $this->video([
            'chapters_status' => 'ready',
            'chapters_source' => 'youtube',
            'chapters_json' => [['title' => 'Chapitre YouTube', 'start_time' => 0]],
        ]);
        $this->addTranscript($video);

        $launcher = $this->mock(ChapterGenerationLauncher::class);
        $launcher->shouldNotReceive('launch');

        $this->postJson("/api/videos/{$video->id}/chapters/generate")
            ->assertOk()
            ->assertJsonPath('started', false)
            ->assertJsonPath('chapters_count', 1)
            ->assertJsonPath('chapters_status', 'ready');
    }

    public function test_it_hides_the_endpoint_for_a_hidden_video(): void
    {
        $video = $this->video(['is_visible' => false]);
        $this->addTranscript($video);

        $launcher = $this->mock(ChapterGenerationLauncher::class);
        $launcher->shouldNotReceive('launch');

        $this->postJson("/api/videos/{$video->id}/chapters/generate")->assertNotFound();
    }

    public function test_an_admin_can_start_the_generation_of_a_hidden_video(): void
    {
        $video = $this->video(['is_visible' => false]);
        $this->addTranscript($video);

        AdminSession::create([
            'token' => hash('sha256', 'admin-token'),
            'expires_at' => now()->addHour(),
        ]);

        $launcher = $this->mock(ChapterGenerationLauncher::class);
        $launcher->shouldReceive('launch')->once()->andReturn(true);

        $this->postJson("/api/videos/{$video->id}/chapters/generate", [], [
            'Authorization' => 'Bearer admin-token',
        ])->assertStatus(202)->assertJsonPath('started', true);
    }
}
