<?php

namespace Tests\Feature;

use App\Models\AdminSession;
use App\Models\Transcript;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPromptTemplateTest extends TestCase
{
    use RefreshDatabase;

    private string $token = 'test-admin-token';

    protected function setUp(): void
    {
        parent::setUp();

        AdminSession::create([
            'token' => hash('sha256', $this->token),
            'expires_at' => now()->addHour(),
        ]);
    }

    public function test_admin_can_list_update_and_reset_ai_prompts(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/admin/prompts')
            ->assertOk()
            ->assertJsonFragment([
                'key' => 'summary_system',
                'label' => 'Resume',
            ]);

        $this->withToken($this->token)
            ->putJson('/api/admin/prompts/summary_system', [
                'content' => 'Summarize with this custom prompt: {transcript}',
            ])
            ->assertOk()
            ->assertJsonPath('prompt.content', 'Summarize with this custom prompt: {transcript}');

        $this->assertDatabaseHas('prompt_templates', [
            'key' => 'summary_system',
            'content' => 'Summarize with this custom prompt: {transcript}',
        ]);

        $this->withToken($this->token)
            ->postJson('/api/admin/prompts/summary_system/reset')
            ->assertOk()
            ->assertJsonPath('prompt.key', 'summary_system');

        $this->assertDatabaseMissing('prompt_templates', [
            'key' => 'summary_system',
            'content' => 'Summarize with this custom prompt: {transcript}',
        ]);
    }

    public function test_translation_rejects_same_source_and_target_language(): void
    {
        $video = Video::create([
            'youtube_id' => 'dQw4w9WgXcQ',
            'url' => 'https://youtu.be/dQw4w9WgXcQ',
            'status' => 'ready',
        ]);

        Transcript::create([
            'video_id' => $video->id,
            'full_text' => 'Bonjour tout le monde.',
            'language' => 'fr-FR',
            'word_count' => 3,
            'segments_json' => [],
        ]);

        $this->postJson("/api/videos/{$video->id}/translate", ['language' => 'fr'])
            ->assertStatus(422)
            ->assertJsonPath('source_language', 'fr')
            ->assertJsonPath('target_language', 'fr');
    }
}
