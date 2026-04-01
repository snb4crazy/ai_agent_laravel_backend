<?php

namespace App\Actions\Stubs;

use App\Actions\Contracts\ActionStubInterface;

class SummarizeTextActionStub implements ActionStubInterface
{
    public function name(): string
    {
        return 'summarize_text';
    }

    public function handle(array $input): array
    {
        $text = trim((string) ($input['text'] ?? data_get($input, 'previous_output.content', '')));

        if ($text === '') {
            return [
                'summary' => '',
                'keywords' => [],
                'status' => 'failed',
                'error' => 'No text provided for summarization.',
            ];
        }

        $sentences = preg_split('/(?<=[.!?])\s+/u', $text) ?: [$text];
        $summary = implode(' ', array_slice($sentences, 0, 2));
        $summary = mb_substr(trim($summary), 0, 400);

        return [
            'summary' => $summary,
            'keywords' => $this->extractKeywords($text),
            'original_length' => mb_strlen($text),
            'status' => 'ok',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function extractKeywords(string $text): array
    {
        $words = preg_split('/[^\pL\pN]+/u', mb_strtolower($text)) ?: [];
        $stopWords = [
            'the', 'and', 'for', 'with', 'this', 'that', 'from', 'have', 'you',
            'are', 'your', 'was', 'were', 'will', 'they', 'about', 'into',
        ];

        $frequency = [];

        foreach ($words as $word) {
            if (mb_strlen($word) < 4 || in_array($word, $stopWords, true)) {
                continue;
            }

            $frequency[$word] = ($frequency[$word] ?? 0) + 1;
        }

        arsort($frequency);

        return array_slice(array_keys($frequency), 0, 5);
    }
}
