<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaticPage;
use App\Support\AdminTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaticPageController extends Controller
{
    public function index(Request $request): View
    {
        $term = AdminTable::term($request);
        $query = StaticPage::query()->when($term !== '', fn ($query) => $query->where(fn ($search) => $search
            ->where('title', 'like', "%{$term}%")
            ->orWhere('slug', 'like', "%{$term}%")));
        AdminTable::sort($query, $request, [
            'title' => 'title', 'slug' => 'slug', 'status' => 'is_published', 'updated' => 'updated_at',
        ], [['id', 'asc']]);

        return view('admin.static-pages.index', [
            'pages' => $query->get(),
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
