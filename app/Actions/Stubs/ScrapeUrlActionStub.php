<?php

namespace App\Actions\Stubs;

use App\Actions\Contracts\ActionStubInterface;
use Illuminate\Support\Facades\Http;

class ScrapeUrlActionStub implements ActionStubInterface
{
    public function name(): string
    {
        return 'scrape_url';
    }

    public function handle(array $input): array
    {
        $url = (string) ($input['url'] ?? '');

        if (! $this->isAllowedUrl($url)) {
            return [
                'url' => $url,
                'status' => 'failed',
                'error' => 'Only public http/https URLs are allowed.',
            ];
        }

        try {
            $response = Http::timeout(12)
                ->accept('text/html,application/xhtml+xml')
                ->get($url);

            if (! $response->successful()) {
                return [
                    'url' => $url,
                    'status' => 'failed',
                    'http_status' => $response->status(),
                    'error' => 'URL returned a non-success status code.',
                ];
            }

            $html = (string) $response->body();
            $title = $this->extractTitle($html);
            $content = $this->toPlainText($html);

            return [
                'url' => $url,
                'title' => $title,
                'content' => mb_substr($content, 0, 5000),
                'content_length' => mb_strlen($content),
                'http_status' => $response->status(),
                'status' => 'ok',
            ];
        } catch (\Throwable $e) {
            return [
                'url' => $url,
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function isAllowedUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $parts = parse_url($url);

        if (! is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        $blocked = ['localhost', '127.0.0.1', '::1'];

        return ! in_array($host, $blocked, true);
    }

    private function extractTitle(string $html): string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches) === 1) {
            return trim(strip_tags((string) $matches[1]));
        }

        return '';
    }

    private function toPlainText(string $html): string
    {
        $withoutScripts = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $html) ?? $html;
        $withoutStyles = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $withoutScripts) ?? $withoutScripts;
        $text = trim(strip_tags($withoutStyles));

        return preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5)) ?? $text;
    }
}
