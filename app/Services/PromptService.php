<?php

namespace App\Services;

use App\Models\PromptTemplate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class PromptService
{
    public const DEFAULTS = [
        'chat_system' => [
            'label' => 'Chat IA',
            'content' => "You are an AI assistant specialized in analyzing YouTube video transcripts. Answer based only on the transcript content. Be concise and accurate.\n\nTranscript:\n{transcript}",
        ],
        'summary_system' => [
            'label' => 'Resume',
            'content' => "You are a helpful assistant. Summarize the following YouTube transcript.\n\nTone: {tone}\nLength: {length}\nOutput language: {language}\n\nTranscript:\n{transcript}",
        ],
        'translate_system' => [
            'label' => 'Traduction',
            'content' => "Translate the following transcript from {source_language} to {target_language}. Preserve the original meaning and structure. Return only the translated text.\n\nTranscript:\n{transcript}",
        ],
    ];

    public function all(): Collection
    {
        if (!$this->storageIsReady()) {
            return collect($this->defaultRows());
        }

        $this->ensureDefaults();

        return PromptTemplate::query()
            ->whereIn('key', array_keys(self::DEFAULTS))
            ->get()
            ->sortBy(fn (PromptTemplate $prompt) => $this->sortOrder($prompt->key))
            ->values();
    }

    public function render(string $key, array $variables): string
    {
        $template = $this->storageIsReady()
            ? $this->findOrCreate($key)->content
            : $this->defaultFor($key)['content'];

        $replacements = [];
        foreach ($variables as $name => $value) {
            $replacements['{' . $name . '}'] = (string) $value;
        }

        return strtr($template, $replacements);
    }

    public function update(string $key, string $content): PromptTemplate
    {
        $this->ensureStorageIsReady();

        $template = $this->findOrCreate($key);
        $template->update(['content' => $content]);

        return $template->fresh();
    }

    public function reset(string $key): PromptTemplate
    {
        $this->ensureStorageIsReady();

        $default = $this->defaultFor($key);
        $template = $this->findOrCreate($key);
        $template->update([
            'label' => $default['label'],
            'content' => $default['content'],
        ]);

        return $template->fresh();
    }

    private function storageIsReady(): bool
    {
        try {
            return Schema::hasTable('prompt_templates');
        } catch (Throwable) {
            return false;
        }
    }

    private function ensureStorageIsReady(): void
    {
        if (!$this->storageIsReady()) {
            throw new RuntimeException('La table des prompts est absente. Lancez php artisan migrate --force dans le conteneur Dokploy.');
        }
    }

    private function defaultRows(): array
    {
        return collect(self::DEFAULTS)
            ->map(fn (array $default, string $key) => [
                'key' => $key,
                'label' => $default['label'],
                'content' => $default['content'],
            ])
            ->sortBy(fn (array $prompt) => $this->sortOrder($prompt['key']))
            ->values()
            ->all();
    }

    private function sortOrder(string $key): int
    {
        return [
            'summary_system' => 1,
            'translate_system' => 2,
            'chat_system' => 3,
        ][$key] ?? 4;
    }

    private function ensureDefaults(): void
    {
        foreach (self::DEFAULTS as $key => $default) {
            PromptTemplate::firstOrCreate(
                ['key' => $key],
                [
                    'label' => $default['label'],
                    'content' => $default['content'],
                ]
            );
        }
    }

    private function findOrCreate(string $key): PromptTemplate
    {
        $default = $this->defaultFor($key);

        return PromptTemplate::firstOrCreate(
            ['key' => $key],
            [
                'label' => $default['label'],
                'content' => $default['content'],
            ]
        );
    }

    private function defaultFor(string $key): array
    {
        if (!array_key_exists($key, self::DEFAULTS)) {
            throw new InvalidArgumentException("Unknown prompt template [{$key}].");
        }

        return self::DEFAULTS[$key];
    }
}
