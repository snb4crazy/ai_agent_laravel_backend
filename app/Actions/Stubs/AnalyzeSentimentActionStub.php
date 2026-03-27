<?php

namespace App\Actions\Stubs;

use App\Actions\Contracts\ActionStubInterface;

class AnalyzeSentimentActionStub implements ActionStubInterface
{
    public function name(): string
    {
        return 'analyze_sentiment';
    }

    public function handle(array $input): array
    {
        $text = (string) ($input['text'] ?? '');
        $score = str_contains(strtolower($text), 'great') ? 0.8 : 0.1;

        return [
            'label' => $score > 0.5 ? 'positive' : 'neutral',
            'score' => $score,
            'status' => 'stubbed',
        ];
    }
}
