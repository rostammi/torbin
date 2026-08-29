<?php

namespace App\Http\Controllers;

use App\Services\Seo\SitemapGenerator;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(SitemapGenerator $sitemap): Response
    {
        return response($sitemap->xml())
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
