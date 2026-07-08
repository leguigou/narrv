<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class DeepseekService
{
    public string $model = 'deepseek-chat';
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = env('DEEPSEEK_API_KEY', '');
        $this->baseUrl = env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com');
    }

    public function chat(string $transcript, $history, string $message): string
    {
        $messages = [
            ['role' => 'system', 'content' => "You are an AI assistant specialized in analyzing YouTube video transcripts. You have access to the full transcript below. Answer the user's questions based only on the transcript content. Be concise and accurate."],
            ['role' => 'system', 'content' => "Transcript:\n{$transcript}"],
        ];

        foreach ($history as $msg) {
            $messages[] = ['role' => $msg->role, 'content' => $msg->content];
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        return $this->callApi($messages) ?? 'Désolé, je n\'ai pas pu répondre.';
    }

    public function translate(string $text, string $targetLanguage): string
    {
        $messages = [
            ['role' => 'system', 'content' => "Translate the following transcript to {$targetLanguage}. Preserve the original meaning and structure. Return only the translated text."],
            ['role' => 'user', 'content' => $text],
        ];

        return $this->callApi($messages) ?? $text;
    }

    public function summarize(string $text, float $temperature, string $tone, string $length): string
    {
        $toneDesc = match ($tone) {
            'formal' => 'formal and professional',
            'casual' => 'casual and conversational',
            'bullet_points' => 'bullet points, structured',
            default => 'neutral and informative',
        };

        $lengthDesc = match ($length) {
            'short' => 'very concise, about 50 words',
            'long' => 'detailed, about 500 words',
            default => 'moderate, about 200 words',
        };

        $messages = [
            ['role' => 'system', 'content' => "You are a helpful assistant. Summarize the following YouTube transcript.\n\nTone: {$toneDesc}\nLength: {$lengthDesc}\n\nTranscript:\n{$text}"],
        ];

        return $this->callApi($messages, $temperature) ?? $text;
    }

    private function callApi(array $messages, float $temperature = 0.3): ?string
    {
        if (empty($this->apiKey)) {
            return null;
        }

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->baseUrl}/v1/chat/completions", [
                    'model' => $this->model,
                    'messages' => $messages,
                    'temperature' => $temperature,
                ]);

            if ($response->successful()) {
                return $response->json()['choices'][0]['message']['content'] ?? null;
            }
        } catch (\Exception $e) {
            logger()->error('DeepSeek API error: ' . $e->getMessage());
        }

        return null;
    }
}
