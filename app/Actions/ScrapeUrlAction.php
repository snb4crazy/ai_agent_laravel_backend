<?php

namespace App\Actions;

use App\Actions\Contracts\ActionInterface;
use Illuminate\Support\Facades\Http;

class ScrapeUrlAction implements ActionInterface
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
                ->withOptions([
                    'allow_redirects' => [
                        'max'         => 5,
                        'strict'      => false,
                        'referer'     => false,
                        'protocols'   => ['http', 'https'],
                        'on_redirect' => function (\Psr\Http\Message\RequestInterface $request, \Psr\Http\Message\ResponseInterface $response, \Psr\Http\Message\UriInterface $uri): void {
                            if (! $this->isAllowedUrl((string) $uri)) {
                                throw new \RuntimeException('Redirect to disallowed URL: '.(string) $uri);
                            }
                        },
                    ],
                ])
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

        return $this->isPublicHost($host);
    }

    private function isPublicHost(string $host): bool
    {
        if ($host === 'localhost') {
            return false;
        }

        // Strip IPv6 brackets if present (e.g. [::1] → ::1).
        $normalizedHost = trim($host, '[]');

        // If the host is an IP literal, check it directly.
        if (filter_var($normalizedHost, FILTER_VALIDATE_IP) !== false) {
            return $this->isPublicIp($normalizedHost);
        }

        // Otherwise resolve the hostname and validate every resulting address.
        $records = dns_get_record($host, DNS_A + DNS_AAAA);

        if ($records === false || $records === []) {
            return false;
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;

            if (! is_string($ip) || ! $this->isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
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
