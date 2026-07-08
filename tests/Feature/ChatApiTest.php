<?php

namespace Tests\Feature;

use App\Models\Transcript;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_chat_history_to_deepseek_once(): void
    {
        config([
            'services.deepseek.api_key' => 'test-key',
            'services.deepseek.base_url' => 'https://api.deepseek.com',
            'services.deepseek.model' => 'deepseek-chat',
        ]);

        Http::fake([
            'api.deepseek.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'The answer is in the transcript.']],
                ],
            ]),
        ]);

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

        $this->postJson("/api/videos/{$video->id}/chat", ['message' => 'What is the answer?'])
            ->assertOk()
            ->assertJsonPath('assistant.content', 'The answer is in the transcript.');

        $this->assertDatabaseHas('chat_messages', [
            'role' => 'assistant',
            'content' => 'The answer is in the transcript.',
        ]);

        Http::assertSent(function ($request) {
            $payload = json_decode($request->body(), true);
            $userMessages = array_filter(
                $payload['messages'],
                fn ($message) => $message['role'] === 'user' && $message['content'] === 'What is the answer?'
            );

            return count($userMessages) === 1;
        });
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
