<?php

namespace App\Http\Controllers;

use App\Models\StaticPage;
use Illuminate\View\View;

class StaticPageController extends Controller
{
    public function show(string $slug): View
    {
        $page = StaticPage::where('slug', $slug)->firstOrFail();
        abort_unless($page->is_published, 404);

        return view('pages.show', compact('page'));
    }
}
