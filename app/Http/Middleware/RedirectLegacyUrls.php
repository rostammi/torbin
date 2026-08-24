<?php

namespace App\Http\Middleware;

use App\Models\LegacyRedirect;
use App\Services\Seo\LegacyUrlNormalizer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectLegacyUrls
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $urls = app(LegacyUrlNormalizer::class);
        $path = $urls->path($request->getPathInfo());
        $redirect = LegacyRedirect::query()->with('tour')->where('old_path', $path)->first();

        if (! $redirect?->tour || ! $redirect->tour->is_active) {
            return $next($request);
        }

        $targetTour = $redirect->tour;
        $visited = [$redirect->id => true];
        for ($hop = 0; $hop < 10; $hop++) {
            $targetPath = $urls->path((string) parse_url($targetTour->publicUrl(), PHP_URL_PATH));
            $next = LegacyRedirect::query()->with('tour')->where('old_path', $targetPath)->first();
            if (! $next?->tour || isset($visited[$next->id]) || ! $next->tour->is_active) {
                break;
            }
            $visited[$next->id] = true;
            $targetTour = $next->tour;
        }

        $target = $targetTour->publicUrl();
        if ($urls->path((string) parse_url($target, PHP_URL_PATH)) === $path) {
            return $next($request);
        }

        LegacyRedirect::whereKey($redirect->id)->increment('hits', 1, ['last_hit_at' => now()]);

        return redirect()->to($target, 301);
    }
}
