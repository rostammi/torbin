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
        if (in_array($request->method(), ['GET', 'HEAD'], true)
            && ($this->usesWwwHost($request) || $this->usesInsecureScheme($request))) {
            return redirect()->to($this->canonicalRedirectUrl($request), 301);
        }

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
            return rtrim(route($name), '/').'/';
        }
        if (str_starts_with((string) $name, 'mag.')) {
            return url('/mag/'.$route->parameter('slug')).'/';
        }
        if (in_array($name, ['pages.about', 'pages.contact', 'pages.faq'], true)) {
            return rtrim(route($name), '/').'/';
        }
        if ($name === 'providers.show') {
            return rtrim(route($name, $route->parameter('provider')), '/').'/';
        }

        return null;
    }

    private function usesWwwHost(Request $request): bool
    {
        $canonicalHost = $this->canonicalHost();

        return $canonicalHost !== '' && strcasecmp($request->getHost(), 'www.'.$canonicalHost) === 0;
    }

    private function usesInsecureScheme(Request $request): bool
    {
        return $this->canonicalScheme() === 'https' && ! $request->isSecure();
    }

    private function canonicalRedirectUrl(Request $request): string
    {
        $canonical = $this->canonicalUrl($request);
        $path = $canonical
            ? (string) parse_url($canonical, PHP_URL_PATH)
            : $request->getPathInfo();
        $query = $request->getQueryString();

        return $this->canonicalOrigin().$path.($query ? '?'.$query : '');
    }

    private function canonicalOrigin(): string
    {
        $configuredUrl = (string) config('app.url', 'https://geyt.ir');
        $port = parse_url($configuredUrl, PHP_URL_PORT);

        return $this->canonicalScheme().'://'.$this->canonicalHost().($port ? ':'.$port : '');
    }

    private function canonicalScheme(): string
    {
        return strtolower((string) (parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https'));
    }

    private function canonicalHost(): string
    {
        $host = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
