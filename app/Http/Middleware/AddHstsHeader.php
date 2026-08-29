<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddHstsHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldSendHsts($request)) {
            return $response;
        }

        $maxAge = max(0, (int) config('security.hsts.max_age', 31_536_000));
        $directives = ['max-age='.$maxAge];

        if ($maxAge > 0 && config('security.hsts.include_subdomains', false)) {
            $directives[] = 'includeSubDomains';
        }

        if ($maxAge > 0 && config('security.hsts.preload', false)) {
            $directives[] = 'preload';
        }

        $response->headers->set('Strict-Transport-Security', implode('; ', $directives));

        return $response;
    }

    private function shouldSendHsts(Request $request): bool
    {
        if (! config('security.hsts.enabled', true)) {
            return false;
        }

        if (strtolower((string) parse_url((string) config('app.url'), PHP_URL_SCHEME)) !== 'https') {
            return false;
        }

        return $request->isSecure()
            || strtolower((string) $request->header('X-Forwarded-Proto')) === 'https';
    }
}
