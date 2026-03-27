<?php

namespace App\Actions\Stubs;

use App\Actions\Contracts\ActionStubInterface;

class ScrapeUrlActionStub implements ActionStubInterface
{
    public function name(): string
    {
        return 'scrape_url';
    }

    public function handle(array $input): array
    {
        $url = (string) ($input['url'] ?? '');

        return [
            'url' => $url,
            'title' => 'Stub page title',
            'content' => 'Stub scraped content for local development.',
            'status' => 'stubbed',
        ];
    }
}
