<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaticPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaticPageController extends Controller
{
    public function index(): View
    {
        return view('admin.static-pages.index', [
            'pages' => StaticPage::query()->orderBy('id')->get(),
        ]);
    }

    public function edit(StaticPage $staticPage): View
    {
        return view('admin.static-pages.edit', ['page' => $staticPage]);
    }

    public function update(Request $request, StaticPage $staticPage): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'content' => ['required', 'string'],
            'is_published' => ['nullable', 'boolean'],
        ]);
        $data['is_published'] = $request->boolean('is_published');
        $staticPage->update($data);

        return redirect()->route('admin.static-pages.edit', $staticPage)
            ->with('success', 'محتوای صفحه ذخیره شد.');
    }
}
