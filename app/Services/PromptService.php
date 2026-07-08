<?php

namespace App\Services;

use App\Models\PromptTemplate;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class PromptService
{
    public const DEFAULTS = [
        'chat_system' => [
            'label' => 'Chat IA',
            'content' => "You are an AI assistant specialized in analyzing YouTube video transcripts. Answer based only on the transcript content. Be concise and accurate.\n\nTranscript:\n{transcript}",
        ],
        'summary_system' => [
            'label' => 'Resume',
            'content' => "You are a helpful assistant. Summarize the following YouTube transcript.\n\nTone: {tone}\nLength: {length}\n\nTranscript:\n{transcript}",
        ],
        'translate_system' => [
            'label' => 'Traduction',
            'content' => "Translate the following transcript from {source_language} to {target_language}. Preserve the original meaning and structure. Return only the translated text.\n\nTranscript:\n{transcript}",
        ],
    ];

    public function all(): Collection
    {
        $this->ensureDefaults();

        return PromptTemplate::query()
            ->whereIn('key', array_keys(self::DEFAULTS))
            ->orderByRaw("case `key` when 'summary_system' then 1 when 'translate_system' then 2 when 'chat_system' then 3 else 4 end")
            ->get();
    }

    public function render(string $key, array $variables): string
    {
        $template = $this->findOrCreate($key)->content;

        $replacements = [];
        foreach ($variables as $name => $value) {
            $replacements['{' . $name . '}'] = (string) $value;
        }

        return strtr($template, $replacements);
    }

    public function update(string $key, string $content): PromptTemplate
    {
        $template = $this->findOrCreate($key);
        $template->update(['content' => $content]);

        return $template->fresh();
    }

    public function reset(string $key): PromptTemplate
    {
        $default = $this->defaultFor($key);
        $template = $this->findOrCreate($key);
        $template->update([
            'label' => $default['label'],
            'content' => $default['content'],
        ]);

        return $template->fresh();
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
