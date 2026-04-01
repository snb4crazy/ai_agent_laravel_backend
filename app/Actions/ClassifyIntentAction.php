<?php

namespace App\Actions;

use App\Actions\Contracts\ActionInterface;

class ClassifyIntentAction implements ActionInterface
{
    public function name(): string
    {
        return 'classify_intent';
    }

    public function handle(array $input): array
    {
        $text = strtolower((string) ($input['text'] ?? ''));

        $intentMap = [
            'refund_request' => ['refund', 'money back', 'charged', 'cancel order'],
            'billing_question' => ['invoice', 'billing', 'payment', 'card'],
            'technical_issue' => ['error', 'bug', 'not working', 'failed', 'crash'],
            'sales_question' => ['price', 'pricing', 'plan', 'demo', 'quote'],
        ];

        foreach ($intentMap as $intent => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    return [
                        'intent' => $intent,
                        'matched_keyword' => $keyword,
                        'confidence' => 0.85,
                        'status' => 'ok',
                    ];
                }
            }
        }

        return [
            'intent' => 'general_question',
            'matched_keyword' => null,
            'confidence' => 0.5,
            'status' => 'ok',
        ];
    }
}
