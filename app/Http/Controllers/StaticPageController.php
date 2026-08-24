<?php

namespace App\Http\Controllers;

use App\Models\StaticPage;
use App\Services\Seo\StructuredDataBuilder;
use Illuminate\View\View;

class StaticPageController extends Controller
{
    public function index(StructuredDataBuilder $seo): View
    {
        $publishedPages = StaticPage::query()
            ->whereIn('slug', StaticPage::MAG_SLUGS)
            ->where('is_published', true)
            ->get()
            ->keyBy('slug');

        $pages = collect(StaticPage::MAG_SLUGS)
            ->map(fn (string $slug) => $publishedPages->get($slug))
            ->filter();
        $structuredData = $seo->magIndex($pages);

        return view('pages.mag-index', compact('pages', 'structuredData'));
    }

    public function show(string $slug, StructuredDataBuilder $seo): View
    {
        $page = StaticPage::where('slug', $slug)->firstOrFail();
        abort_unless($page->is_published, 404);
        $structuredData = $seo->staticPage($page);

        return view('pages.show', compact('page', 'structuredData'));
    }
}
