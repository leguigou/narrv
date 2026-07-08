<?php

namespace App\Services;

class SummaryService
{
    private DeepseekService $deepseek;

    public function __construct()
    {
        $this->deepseek = new DeepseekService();
    }

    public function generate(string $transcriptText, float $temperature, string $tone, string $length, string $language): string
    {
        return $this->deepseek->summarize($transcriptText, $temperature, $tone, $length, $language);
    }
}
