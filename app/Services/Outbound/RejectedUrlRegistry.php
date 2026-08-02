<?php

namespace App\Services\Outbound;

class RejectedUrlRegistry
{
    public function contains(array $rejectedUrls, ?string $candidate): bool
    {
        if (! $candidate) {
            return false;
        }

        $fingerprint = $this->fingerprint($candidate);

        return collect($rejectedUrls)
            ->contains(fn (string $url) => $this->fingerprint($url) === $fingerprint);
    }

    public function add(array $rejectedUrls, ?string $candidate): array
    {
        if (! $candidate || $this->contains($rejectedUrls, $candidate)) {
            return array_values($rejectedUrls);
        }

        return [...array_values($rejectedUrls), $candidate];
    }

    private function fingerprint(string $url): string
    {
        $parts = parse_url(trim($url));
        if (! isset($parts['scheme'], $parts['host'])) {
            return trim($url);
        }

        $query = [];
        parse_str($parts['query'] ?? '', $query);
        foreach (array_keys($query) as $key) {
            if (preg_match('/^(?:utm_.+|gclid|fbclid)$/i', (string) $key)) {
                unset($query[$key]);
            }
        }
        ksort($query);

        $path = '/'.ltrim(rawurldecode($parts['path'] ?? '/'), '/');
        $path = $path === '/' ? $path : rtrim($path, '/');
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $normalizedQuery = http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        return mb_strtolower($parts['scheme'].'://'.$parts['host'].$port).$path
            .($normalizedQuery !== '' ? '?'.$normalizedQuery : '');
    }
}
