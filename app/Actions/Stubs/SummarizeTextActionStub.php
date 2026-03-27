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
        $text = (string) ($input['text'] ?? '');

        return [
            'summary' => mb_substr($text, 0, 120),
            'status' => 'stubbed',
        ];
    }
}
