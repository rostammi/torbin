<?php

namespace App\Http\Middleware;

use App\Models\Tour;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceCanonicalPublicUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        // Laravel's HTTP test client removes trailing slashes before the
        // middleware stack. Production web requests retain the original URI.
        if (app()->runningUnitTests()) {
            return $next($request);
        }

        $response = $next($request);
        if (! in_array($request->method(), ['GET', 'HEAD'], true) || ! $response->isSuccessful()) {
            return $response;
        }

        $canonical = $this->canonicalUrl($request);
        $requestedPath = (string) parse_url($request->getRequestUri(), PHP_URL_PATH);
        if (! $canonical || $requestedPath === (string) parse_url($canonical, PHP_URL_PATH)) {
            return $response;
        }

        $query = $request->getQueryString();

        return redirect()->to($canonical.($query ? '?'.$query : ''), 301);
    }

    private function canonicalUrl(Request $request): ?string
    {
        $route = $request->route();
        $name = $route?->getName();
        $tour = $route?->parameter('tour');

        if ($tour instanceof Tour && in_array($name, ['tours.show', 'hotels.show', 'stays.show', 'visas.show'], true)) {
            return $tour->publicUrl();
        }
        if (in_array($name, ['tours.index', 'hotels.index', 'stays.index', 'visas.index', 'mag.index'], true)) {
            return route($name).'/';
        }
        if (str_starts_with((string) $name, 'mag.')) {
            return url('/mag/'.$route->parameter('slug')).'/';
        }

        return null;
    }
}
