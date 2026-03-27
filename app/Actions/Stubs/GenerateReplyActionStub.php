<?php

namespace App\Actions\Stubs;

use App\Actions\Contracts\ActionStubInterface;

class GenerateReplyActionStub implements ActionStubInterface
{
    public function name(): string
    {
        return 'generate_reply';
    }

    public function handle(array $input): array
    {
        $text = (string) ($input['text'] ?? '');

        return [
            'reply' => 'Stub reply: Thanks for your message. We will get back to you soon.',
            'source_excerpt' => mb_substr($text, 0, 80),
            'status' => 'stubbed',
        ];
    }
}
