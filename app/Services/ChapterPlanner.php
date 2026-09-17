<?php

namespace App\Services;

use RuntimeException;

/**
 * Prepares the transcript sent to the model and turns its answer into chapters
 * covering the whole video (start / end / duration for every part).
 */
class ChapterPlanner
{
    /**
     * Block sizes (in seconds) tried from the most detailed to the most compact
     * one, so that long videos still fit in the prompt budget without losing
     * any part of the timeline.
     */
    private const BLOCK_SIZES = [20, 30, 45, 60, 90, 120, 180, 300, 600];

    /**
     * Fallback used when even the largest block does not fit: the text of each
     * block is shortened instead of dropping the end of the video.
     */
    private const COMPACT_BLOCK_SIZE = 600;

    private const COMPACT_TEXT_LENGTH = 400;

    private const MINIMUM_TEXT_LENGTH = 60;

    public function __construct(private DeepseekService $ai)
    {
    }

    /**
     * @param  list<array{start?: mixed, end?: mixed, text?: mixed}>  $segments
     * @return list<array{title: string, start_time: float, end_time: float, duration: float}>
     */
    public function plan(array $segments, float $duration): array
    {
        $outline = $this->outline($segments);

        if ($outline === '') {
            throw new RuntimeException('The transcript does not contain any usable text.');
        }

        return $this->withDurations($this->ai->generateChapters($outline, $duration), $duration);
    }

    /**
     * Compact timestamped view of the whole video: one line per time block.
     *
     * @param  list<array{start?: mixed, end?: mixed, text?: mixed}>  $segments
     */
    public function outline(array $segments): string
    {
        $budget = max(2000, (int) config('services.deepseek.max_input_characters', 45000));

        foreach (self::BLOCK_SIZES as $blockSize) {
            $outline = $this->buildOutline($segments, $blockSize);

            if ($this->length($outline) <= $budget) {
                return $outline;
            }
        }

        // Toujours trop long : on élargit les blocs et on coupe le texte de
        // chacun, afin de couvrir la vidéo entière dans le budget disponible.
        $blockSize = self::COMPACT_BLOCK_SIZE;
        $textLength = self::COMPACT_TEXT_LENGTH;

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $outline = $this->buildOutline($segments, $blockSize, $textLength);

            if ($this->length($outline) <= $budget || $textLength <= self::MINIMUM_TEXT_LENGTH) {
                return $outline;
            }

            $blockSize = min(2400, $blockSize * 2);
            $textLength = max(self::MINIMUM_TEXT_LENGTH, (int) ($textLength * 0.75));
        }

        return $outline;
    }

    /**
     * @param  list<array{title: string, start_time: float}>  $chapters
     * @return list<array{title: string, start_time: float, end_time: float, duration: float}>
     */
    public function withDurations(array $chapters, float $duration): array
    {
        $last = count($chapters) - 1;

        foreach ($chapters as $index => &$chapter) {
            $start = max(0.0, (float) $chapter['start_time']);
            $end = $index === $last
                ? max($start, $duration)
                : max($start, (float) $chapters[$index + 1]['start_time']);

            $chapter['start_time'] = $start;
            $chapter['end_time'] = $end;
            $chapter['duration'] = round($end - $start, 2);
        }
        unset($chapter);

        return $chapters;
    }

    /**
     * @param  list<array{start?: mixed, end?: mixed, text?: mixed}>  $segments
     */
    private function buildOutline(array $segments, int $blockSeconds, ?int $textLength = null): string
    {
        $lines = [];
        $blockIndex = null;
        $blockStart = 0.0;
        $buffer = [];

        foreach ($segments as $segment) {
            $text = trim((string) ($segment['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            $start = max(0.0, (float) ($segment['start'] ?? 0));
            $index = (int) floor($start / $blockSeconds);

            if ($blockIndex === null) {
                $blockIndex = $index;
                $blockStart = $start;
            } elseif ($index !== $blockIndex) {
                $lines[] = $this->line($blockStart, $buffer, $textLength);
                $blockIndex = $index;
                $blockStart = $start;
                $buffer = [];
            }

            $buffer[] = $text;
        }

        if ($buffer !== []) {
            $lines[] = $this->line($blockStart, $buffer, $textLength);
        }

        return trim(implode("\n", $lines));
    }

    /**
     * @param  list<string>  $texts
     */
    private function line(float $start, array $texts, ?int $textLength = null): string
    {
        $text = preg_replace('/\s+/u', ' ', implode(' ', $texts)) ?? implode(' ', $texts);
        $text = trim($text);

        if ($textLength !== null && $this->length($text) > $textLength) {
            $text = rtrim($this->substring($text, 0, $textLength)) . '…';
        }

        return '[' . $this->formatTime($start) . '] ' . $text;
    }

    private function substring(string $value, int $offset, ?int $length = null): string
    {
        return function_exists('mb_substr')
            ? mb_substr($value, $offset, $length)
            : substr($value, $offset, $length);
    }

    private function formatTime(float $seconds): string
    {
        $seconds = (int) floor(max(0, $seconds));
        $minutes = intdiv($seconds, 60);
        $hours = intdiv($minutes, 60);
        $minutes %= 60;
        $seconds %= 60;

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $seconds)
            : sprintf('%d:%02d', $minutes, $seconds);
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }
}
