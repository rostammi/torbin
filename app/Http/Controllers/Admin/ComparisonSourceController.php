<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ScanComparisonSource;
use App\Models\ComparisonSource;
use App\Models\SyncRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ComparisonSourceController extends Controller
{
    public function index(): View
    {
        return view('admin.comparison-sources.index', [
            'sources' => ComparisonSource::query()->latest()->paginate(20),
            'categories' => config('comparison.categories'),
        ]);
    }

    public function create(): View
    {
        return view('admin.comparison-sources.create', [
            'source' => new ComparisonSource,
            'categories' => config('comparison.categories'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $source = ComparisonSource::create($data);
        if ($source->is_active) {
            $this->queueScan($request, $source);
        }

        return redirect()->route('admin.comparison-sources.index')
            ->with('success', $source->is_active
                ? "منبع «{$source->name}» اضافه شد و اسکن آن در صف اجرا قرار گرفت."
                : "منبع «{$source->name}» به‌صورت غیرفعال اضافه شد.");
    }

    public function edit(ComparisonSource $comparisonSource): View
    {
        return view('admin.comparison-sources.edit', [
            'source' => $comparisonSource,
            'categories' => config('comparison.categories'),
        ]);
    }

    public function update(Request $request, ComparisonSource $comparisonSource): RedirectResponse
    {
        $comparisonSource->update($this->validated($request, $comparisonSource));

        return redirect()->route('admin.comparison-sources.edit', $comparisonSource)
            ->with('success', 'تنظیمات منبع ذخیره شد.');
    }

    public function destroy(ComparisonSource $comparisonSource): RedirectResponse
    {
        $comparisonSource->delete();

        return back()->with('success', 'منبع از فهرست مدیریت منابع حذف شد.');
    }

    public function scan(Request $request, ComparisonSource $comparisonSource): RedirectResponse
    {
        if (! $comparisonSource->is_active) {
            return back()->with('error', 'برای اسکن، ابتدا منبع را فعال کنید.');
        }

        $alreadyRunning = SyncRun::query()
            ->where('type', 'scan_comparison_source')
            ->where('status', 'running')
            ->where('details->source_id', $comparisonSource->id)
            ->exists();
        if ($alreadyRunning) {
            return back()->with('error', 'اسکن این منبع هم‌اکنون در حال اجراست.');
        }

        $this->queueScan($request, $comparisonSource);

        return back()->with('success', 'اسکن منبع در صف اجرا قرار گرفت.');
    }

    private function queueScan(Request $request, ComparisonSource $source): SyncRun
    {
        $run = SyncRun::create([
            'user_id' => $request->user()->id,
            'type' => 'scan_comparison_source',
            'status' => 'running',
            'total' => 1,
            'successful' => 0,
            'failed' => 0,
            'details' => ['source_id' => $source->id],
            'started_at' => now(),
        ]);
        ScanComparisonSource::dispatch($source->id, $run->id);

        return $run;
    }

    private function validated(Request $request, ?ComparisonSource $source = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'homepage_url' => ['required', 'url:http,https', 'max:2000'],
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['required', 'string', 'distinct', Rule::in(array_keys(config('comparison.categories')))],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['homepage_url'] = $this->normalizeUrl($data['homepage_url']);
        $data['homepage_hash'] = hash('sha256', $data['homepage_url']);
        $data['is_active'] = $request->boolean('is_active');

        if (ComparisonSource::query()
            ->where('homepage_hash', $data['homepage_hash'])
            ->when($source, fn ($query) => $query->whereKeyNot($source->id))
            ->exists()) {
            throw ValidationException::withMessages([
                'homepage_url' => 'این آدرس قبلاً در مدیریت منابع ثبت شده است؛ همان منبع را ویرایش و دسته‌هایش را انتخاب کنید.',
            ]);
        }

        return $data;
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);
        $path = $parts['path'] ?? '';

        return $path === '/' ? rtrim($url, '/') : $url;
    }
}
