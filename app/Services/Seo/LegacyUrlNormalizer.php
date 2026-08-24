<?php

namespace App\Services\Seo;

class LegacyUrlNormalizer
{
    public function source(string $url): ?array
    {
        $url = trim($url);
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (! in_array($host, ['geyt.ir', 'www.geyt.ir'], true)) {
            return null;
        }

        $path = $this->path((string) parse_url($url, PHP_URL_PATH));
        if ($path === '/') {
            return null;
        }

        return [
            'url' => 'https://geyt.ir'.$path,
            'path' => $path,
        ];
    }

    public function path(string $path): string
    {
        $path = rawurldecode($path);
        $path = preg_replace('~/+~', '/', $path) ?? $path;
        $path = '/'.trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/').'/';
    }

    public function isDetailPath(string $path): bool
    {
        return (bool) preg_match('~^/(tour|hotel|accommodation|visa)/[^/]+/$~u', $path);
    }
}
