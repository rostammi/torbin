<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tour;
use App\Services\PriceCrawler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TourController extends Controller
{
    public function index(): View
    {
        $tours = Tour::withCount('priceSources')->latest()->paginate(15);

        return view('admin.tours.index', compact('tours'));
    }

    public function create(): View
    {
        return view('admin.tours.create', ['tour' => new Tour]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tour = Tour::create($this->validated($request));

        return redirect()->route('admin.tours.edit', $tour)->with('success', 'تور ساخته شد؛ حالا منابع قیمت را اضافه کنید.');
    }

    public function edit(Tour $tour): View
    {
        $tour->load(['priceSources' => fn ($query) => $query->latest()]);

        return view('admin.tours.edit', compact('tour'));
    }

    public function update(Request $request, Tour $tour): RedirectResponse
    {
        $tour->update($this->validated($request, $tour));

        return back()->with('success', 'اطلاعات تور ذخیره شد.');
    }

    public function destroy(Tour $tour): RedirectResponse
    {
        if ($tour->cover_image) {
            Storage::disk('public')->delete($tour->cover_image);
        }
        Storage::disk('public')->delete($tour->gallery ?? []);
        $tour->delete();

        return redirect()->route('admin.tours.index')->with('success', 'تور حذف شد.');
    }

    public function crawl(Tour $tour, PriceCrawler $crawler): RedirectResponse
    {
        $sources = $tour->priceSources()->where('is_active', true)->where('extraction_type', '!=', 'manual')->get();
        $success = $sources->filter(fn ($source) => $crawler->crawl($source))->count();

        return back()->with('success', "بررسی قیمت‌ها تمام شد: {$success} منبع از {$sources->count()} منبع موفق بود.");
    }

    private function validated(Request $request, ?Tour $tour = null): array
    {
        $request->merge([
            'slug' => Str::slug($request->input('slug') ?: $request->input('title')) ?: Str::random(10),
        ]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:160', Rule::unique('tours')->ignore($tour)],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'description' => ['required', 'string'],
            'cover_image' => ['nullable', 'image', 'max:5120'],
            'gallery' => ['nullable', 'array', 'max:12'],
            'gallery.*' => ['image', 'max:8192'],
            'video_url' => ['nullable', 'url', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        if ($request->hasFile('cover_image')) {
            if ($tour?->cover_image) {
                Storage::disk('public')->delete($tour->cover_image);
            }
            $data['cover_image'] = $request->file('cover_image')->store('tours/covers', 'public');
        } else {
            unset($data['cover_image']);
        }

        $gallery = $tour?->gallery ?? [];
        foreach ($request->file('gallery', []) as $image) {
            $gallery[] = $image->store('tours/gallery', 'public');
        }
        $data['gallery'] = $gallery;

        return $data;
    }
}
