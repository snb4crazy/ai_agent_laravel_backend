<?php

namespace App\Actions;

use App\Actions\Contracts\ActionInterface;

class GenerateReplyAction implements ActionInterface
{
    public function name(): string
    {
        return 'generate_reply';
    }

    public function handle(array $input): array
    {
        $text = trim((string) ($input['text'] ?? ''));
        $intent = (string) ($input['intent'] ?? data_get($input, 'previous_output.intent', 'general_question'));
        $sentiment = (string) ($input['sentiment'] ?? data_get($input, 'previous_output.label', 'neutral'));

        $opening = match (true) {
            $sentiment === 'negative' => 'I am sorry you are facing this.',
            $sentiment === 'positive' => 'Thanks for the positive message.',
            default => 'Thanks for reaching out.',
        };

        $actionLine = match ($intent) {
            'refund_request' => 'Please share your order ID and we will check refund options.',
            'billing_question' => 'Please share your invoice number so we can verify billing details.',
            'technical_issue' => 'Please share error steps/screenshots and we will investigate quickly.',
            'sales_question' => 'We can help with pricing and plans. Tell us your expected usage.',
            default => 'Please share more details so we can assist properly.',
        };

        return [
            'reply' => trim($opening.' '.$actionLine),
            'intent_used' => $intent,
            'sentiment_used' => $sentiment,
            'source_excerpt' => mb_substr($text, 0, 160),
            'status' => 'ok',
        ];
    }
}
