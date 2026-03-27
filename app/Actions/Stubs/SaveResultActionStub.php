<?php

namespace App\Actions\Stubs;

use App\Actions\Contracts\ActionStubInterface;

class SaveResultActionStub implements ActionStubInterface
{
    public function name(): string
    {
        return 'save_result';
    }

    public function handle(array $input): array
    {
        return [
            'saved' => true,
            'reference' => 'stub-'.substr(sha1(json_encode($input)), 0, 10),
            'status' => 'stubbed',
        ];
    }
}
