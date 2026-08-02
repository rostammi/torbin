<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecureExternalLinks
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $contentType = (string) $response->headers->get('Content-Type');
        $content = $response->getContent();

        if (! str_contains($contentType, 'text/html') || ! is_string($content) || $content === '') {
            return $response;
        }

        $secured = preg_replace_callback('/<a\b[^>]*>/iu', function (array $match) use ($request) {
            $tag = $match[0];
            if (! preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/isu', $tag, $hrefMatch)) {
                return $tag;
            }

            if (! $this->isExternal(html_entity_decode($hrefMatch[2], ENT_QUOTES | ENT_HTML5), $request)) {
                return $tag;
            }

            return $this->withRequiredRel($tag);
        }, $content);

        if (is_string($secured)) {
            $original = property_exists($response, 'original') ? $response->original : null;
            $response->setContent($secured);
            if (property_exists($response, 'original')) {
                $response->original = $original;
            }
        }

        return $response;
    }

    private function isExternal(string $href, Request $request): bool
    {
        if (! preg_match('~^(?:https?:)?//~i', $href)) {
            return false;
        }

        $host = parse_url(str_starts_with($href, '//') ? 'https:'.$href : $href, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }

        $normalize = fn (string $value) => preg_replace('/^www\./i', '', mb_strtolower($value));

        return $normalize($host) !== $normalize($request->getHost());
    }

    private function withRequiredRel(string $tag): string
    {
        if (preg_match('/\brel\s*=\s*(["\'])(.*?)\1/isu', $tag, $relMatch)) {
            $tokens = preg_split('/\s+/', trim($relMatch[2])) ?: [];
            $tokens = array_values(array_unique([...array_filter($tokens), 'nofollow', 'noopener']));
            $replacement = 'rel='.$relMatch[1].implode(' ', $tokens).$relMatch[1];

            return substr_replace($tag, $replacement, strpos($tag, $relMatch[0]), strlen($relMatch[0]));
        }

        return substr($tag, 0, -1).' rel="nofollow noopener">';
    }
}
