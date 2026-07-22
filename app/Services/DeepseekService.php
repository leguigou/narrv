<?php

namespace App\Services;

use RuntimeException;

class DeepseekService
{
    public string $model;
    private string $apiKey;
    private string $baseUrl;
    private int $timeout;
    private int $connectTimeout;
    private PromptService $prompts;

    public function __construct(?PromptService $prompts = null)
    {
        $this->apiKey = (string) config('services.deepseek.api_key', '');
        $this->baseUrl = rtrim((string) config('services.deepseek.base_url', 'https://api.deepseek.com'), '/');
        $this->model = (string) config('services.deepseek.model', 'deepseek-chat');
        $this->timeout = max(1, (int) config('services.deepseek.timeout', 180));
        $this->connectTimeout = max(1, min(
            $this->timeout,
            (int) config('services.deepseek.connect_timeout', 10)
        ));
        $this->prompts = $prompts ?? app(PromptService::class);
    }

    public function chat(string $transcript, $history): string
    {
        $messages = [
            ['role' => 'system', 'content' => $this->prompts->render('chat_system', [
                'transcript' => $this->trimToBudget($transcript),
            ])],
        ];

        foreach ($history as $msg) {
            $messages[] = ['role' => $msg->role, 'content' => $msg->content];
        }

        return $this->callApi($messages);
    }

    public function translate(
        string $text,
        string $targetLanguage,
        ?string $sourceLanguage = null,
        ?callable $onChunk = null
    ): string
    {
        $translations = [];
        $chunks = $this->translationChunks($text);
        $total = count($chunks);

        foreach ($chunks as $index => $chunk) {
            $messages = [
                ['role' => 'system', 'content' => $this->prompts->render('translate_system', [
                    'source_language' => $this->languageName($sourceLanguage) ?? 'the detected source language',
                    'target_language' => $this->languageName($targetLanguage) ?? $targetLanguage,
                    'transcript' => $chunk,
                ])],
            ];

            $translation = trim((string) $this->callApi($messages));
            $translations[] = $translation;

            if ($onChunk !== null) {
                $onChunk($translation, $index + 1, $total);
            }
        }

        return implode("\n\n", $translations);
    }

    public function translationChunkCount(string $text): int
    {
        return count($this->translationChunks($text));
    }

    public function summarize(string $text, float $temperature, string $tone, string $length, string $language = 'fr'): string
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

        $languageName = $this->languageName($language) ?? $language;

        $prompt = $this->prompts->render('summary_system', [
            'tone' => $toneDesc,
            'length' => $lengthDesc,
            'language' => $languageName,
            'transcript' => $this->trimToBudget($text),
        ]);

        $messages = [
            ['role' => 'system', 'content' => "{$prompt}\n\nWrite the summary in {$languageName}."],
        ];

        return $this->callApi($messages, $temperature);
    }

    protected function callApi(array $messages, float $temperature = 0.3): ?string
    {
        if (empty($this->apiKey)) {
            logger()->error('DeepSeek API key is not configured.', [
                'source' => 'deepseek',
                'base_url' => $this->baseUrl,
                'model' => $this->model,
            ]);

            throw new RuntimeException('DeepSeek API key is not configured.');
        }

        $endpoint = $this->baseUrl;
        if (!str_ends_with($endpoint, '/v1')) {
            $endpoint .= '/v1';
        }

        try {
            $payload = json_encode([
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => $temperature,
            ]);

            $ch = curl_init("{$endpoint}/chat/completions");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->apiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                logger()->error('DeepSeek API error: ' . $error, [
                    'source' => 'deepseek',
                    'timeout' => $this->timeout,
                    'connect_timeout' => $this->connectTimeout,
                ]);
                throw new RuntimeException('DeepSeek API request failed: ' . $error);
            }

            if ($httpCode === 200) {
                $data = json_decode($response, true);
                $content = $data['choices'][0]['message']['content'] ?? null;

                if (!is_string($content) || trim($content) === '') {
                    logger()->error('DeepSeek API returned an empty completion', ['body' => $response]);
                    throw new RuntimeException('DeepSeek API returned an empty response.');
                }

                return $content;
            }

            $errorBody = $this->limitString(trim(strip_tags((string) $response)), 300);
            logger()->error('DeepSeek API HTTP error', ['code' => $httpCode, 'body' => $response]);
            throw new RuntimeException("DeepSeek API returned HTTP {$httpCode}" . ($errorBody ? ": {$errorBody}" : ''));
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            logger()->error('DeepSeek API error: ' . $e->getMessage());
            throw new RuntimeException('DeepSeek API request failed.', previous: $e);
        }
    }

    private function trimToBudget(string $text): string
    {
        $maxCharacters = (int) config('services.deepseek.max_input_characters', 45000);

        if ($this->stringLength($text) <= $maxCharacters) {
            return $text;
        }

        return $this->limitString($text, $maxCharacters) . "\n\n[Transcript truncated for model context length.]";
    }

    /**
     * Split a transcript without losing content, preferring natural text boundaries.
     *
     * @return list<string>
     */
    private function translationChunks(string $text): array
    {
        $configuredLimit = max(1, (int) config('services.deepseek.translation_chunk_characters', 12000));
        $inputLimit = max(1, (int) config('services.deepseek.max_input_characters', 45000));
        $limit = min($configuredLimit, $inputLimit);
        $remaining = trim($text);
        $chunks = [];

        while ($this->stringLength($remaining) > $limit) {
            $candidate = $this->limitString($remaining, $limit);
            $cut = $this->naturalCutPosition($candidate, $limit);
            $chunks[] = trim($this->substring($remaining, 0, $cut));
            $remaining = ltrim($this->substring($remaining, $cut));
        }

        if ($remaining !== '') {
            $chunks[] = $remaining;
        }

        return $chunks;
    }

    private function naturalCutPosition(string $candidate, int $limit): int
    {
        $minimum = (int) floor($limit * 0.6);

        foreach (["\n\n", "\n", '. ', '! ', '? ', '; ', ', ', ' '] as $separator) {
            $position = $this->lastPosition($candidate, $separator);

            if ($position !== false && $position >= $minimum) {
                return $position + $this->stringLength($separator);
            }
        }

        return $limit;
    }

    private function languageName(?string $language): ?string
    {
        if (!$language) {
            return null;
        }

        $code = strtolower(str_replace('_', '-', $language));
        $base = explode('-', $code)[0] ?? $code;

        return [
            'en' => 'English',
            'fr' => 'French',
            'es' => 'Spanish',
            'it' => 'Italian',
            'de' => 'German',
        ][$base] ?? $language;
    }

    private function stringLength(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }

    private function limitString(string $value, int $limit): string
    {
        if ($limit <= 0) {
            return '';
        }

        return function_exists('mb_substr') ? mb_substr($value, 0, $limit) : substr($value, 0, $limit);
    }

    private function substring(string $value, int $offset, ?int $length = null): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, $offset, $length);
        }

        return $length === null ? substr($value, $offset) : substr($value, $offset, $length);
    }

    private function lastPosition(string $haystack, string $needle): int|false
    {
        return function_exists('mb_strrpos')
            ? mb_strrpos($haystack, $needle)
            : strrpos($haystack, $needle);
    }
}
