<?php

namespace App\Actions\Stubs;

use App\Actions\Contracts\ActionStubInterface;

class ClassifyIntentActionStub implements ActionStubInterface
{
    public function name(): string
    {
        return 'classify_intent';
    }

    public function handle(array $input): array
    {
        $text = strtolower((string) ($input['text'] ?? ''));

        $intent = str_contains($text, 'refund') ? 'refund_request' : 'general_question';

        return [
            'intent' => $intent,
            'confidence' => 0.7,
            'status' => 'stubbed',
        ];
    }
}
