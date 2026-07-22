<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use App\Models\Agency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdvertisementController extends Controller
{
    public function index(Request $request): View
    {
        $placement = $request->string('placement')->toString();
        $advertisements = Advertisement::query()
            ->with('agency')
            ->when($placement !== '', fn ($query) => $query->where('placement', $placement))
            ->orderByDesc('is_active')
            ->orderByDesc('priority')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.advertisements.index', compact('advertisements', 'placement'));
    }

    public function create(): View
    {
        return view('admin.advertisements.create', [
            'advertisement' => new Advertisement,
            'agencies' => Agency::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Advertisement::create($this->validated($request));

        return redirect()->route('admin.advertisements.index')->with('success', 'تبلیغ ساخته شد.');
    }

    public function edit(Advertisement $advertisement): View
    {
        $agencies = Agency::orderBy('name')->get(['id', 'name']);

        return view('admin.advertisements.edit', compact('advertisement', 'agencies'));
    }

    public function update(Request $request, Advertisement $advertisement): RedirectResponse
    {
        $advertisement->update($this->validated($request, $advertisement));

        return back()->with('success', 'تبلیغ به‌روزرسانی شد.');
    }

    public function destroy(Advertisement $advertisement): RedirectResponse
    {
        if ($advertisement->image_path) {
            Storage::disk('public')->delete($advertisement->image_path);
        }
        $advertisement->delete();

        return redirect()->route('admin.advertisements.index')->with('success', 'تبلیغ حذف شد.');
    }

    private function validated(Request $request, ?Advertisement $advertisement = null): array
    {
        $data = $request->validate([
            'agency_id' => ['nullable', Rule::exists('agencies', 'id')],
            'name' => ['required', 'string', 'max:150'],
            'advertiser_name' => ['nullable', 'required_without:agency_id', 'string', 'max:150'],
            'placement' => ['required', Rule::in(array_keys(Advertisement::PLACEMENTS))],
            'title' => ['nullable', 'string', 'max:180'],
            'subtitle' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'image', 'max:5120'],
            'destination_url' => ['required', 'url:http,https', 'max:2000'],
            'cta_text' => ['required', 'string', 'max:60'],
            'priority' => ['required', 'integer', 'min:-1000', 'max:1000'],
            'contract_amount' => ['nullable', 'integer', 'min:0'],
            'contract_currency' => ['required', Rule::in(['تومان', 'ریال'])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        if (! empty($data['agency_id'])) {
            $data['advertiser_name'] = Agency::findOrFail($data['agency_id'])->name;
        }
        unset($data['image']);

        if ($request->hasFile('image')) {
            if ($advertisement?->image_path) {
                Storage::disk('public')->delete($advertisement->image_path);
            }
            $data['image_path'] = $request->file('image')->store('advertisements', 'public');
        }

        return $data;
    }
}
