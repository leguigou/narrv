<?php

namespace Tests\Feature;

use App\Models\Transcript;
use App\Models\Video;
use App\Services\DeepseekService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_chat_history_to_deepseek_once(): void
    {
        $video = Video::create([
            'youtube_id' => 'dQw4w9WgXcQ',
            'url' => 'https://youtu.be/dQw4w9WgXcQ',
            'status' => 'ready',
        ]);

        Transcript::create([
            'video_id' => $video->id,
            'full_text' => 'This transcript explains the answer.',
            'language' => 'en',
            'word_count' => 6,
            'segments_json' => [],
        ]);

        $this->mock(DeepseekService::class, function ($mock) {
            $mock->shouldReceive('chat')
                ->once()
                ->withArgs(function (string $transcript, $history) {
                    $userMessages = $history->filter(
                        fn ($message) => $message->role === 'user' && $message->content === 'What is the answer?'
                    );

                    return $transcript === 'This transcript explains the answer.'
                        && $userMessages->count() === 1;
                })
                ->andReturn('The answer is in the transcript.');
        });

        $this->postJson("/api/videos/{$video->id}/chat", ['message' => 'What is the answer?'])
            ->assertOk()
            ->assertJsonPath('assistant.content', 'The answer is in the transcript.');

        $this->assertDatabaseHas('chat_messages', [
            'role' => 'assistant',
            'content' => 'The answer is in the transcript.',
        ]);
    }

    public function test_it_returns_bad_gateway_when_deepseek_is_not_configured(): void
    {
        config(['services.deepseek.api_key' => '']);

        $video = Video::create([
            'youtube_id' => 'dQw4w9WgXcQ',
            'url' => 'https://youtu.be/dQw4w9WgXcQ',
            'status' => 'ready',
        ]);

        Transcript::create([
            'video_id' => $video->id,
            'full_text' => 'Transcript text.',
            'language' => 'en',
            'word_count' => 2,
            'segments_json' => [],
        ]);

        $this->postJson("/api/videos/{$video->id}/chat", ['message' => 'Hello'])
            ->assertStatus(502)
            ->assertJsonPath('error', 'DeepSeek API key is not configured.');
    }
}
