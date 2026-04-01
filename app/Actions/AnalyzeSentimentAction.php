<?php

namespace App\Actions;

use App\Actions\Contracts\ActionInterface;

class AnalyzeSentimentAction implements ActionInterface
{
    public function name(): string
    {
        return 'analyze_sentiment';
    }

    public function handle(array $input): array
    {
        $text = strtolower((string) ($input['text'] ?? ''));

        $positiveWords = ['great', 'good', 'excellent', 'love', 'happy', 'awesome', 'thanks', 'perfect'];
        $negativeWords = ['bad', 'terrible', 'awful', 'hate', 'angry', 'broken', 'refund', 'issue'];

        $positiveHits = $this->countHits($text, $positiveWords);
        $negativeHits = $this->countHits($text, $negativeWords);

        $totalHits = $positiveHits + $negativeHits;
        $score = $totalHits > 0 ? round(($positiveHits - $negativeHits) / $totalHits, 3) : 0.0;

        $label = 'neutral';
        if ($score > 0.2) {
            $label = 'positive';
        } elseif ($score < -0.2) {
            $label = 'negative';
        }

        return [
            'label' => $label,
            'score' => $score,
            'positive_hits' => $positiveHits,
            'negative_hits' => $negativeHits,
            'status' => 'ok',
        ];
    }

    /**
     * @param  array<int, string>  $dictionary
     */
    private function countHits(string $text, array $dictionary): int
    {
        $hits = 0;

        foreach ($dictionary as $word) {
            if (str_contains($text, $word)) {
                $hits++;
            }
        }

        return $hits;
    }
}
