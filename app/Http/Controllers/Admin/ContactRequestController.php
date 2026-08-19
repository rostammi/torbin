<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PriceAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContactRequestController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(PriceAlert::CONTACT_STATUSES))],
            'origin' => ['nullable', Rule::in(array_keys(PriceAlert::ORIGINS))],
        ]);
        $status = $data['status'] ?? '';
        $origin = $data['origin'] ?? '';
        $requests = PriceAlert::query()
            ->with('tour')
            ->when($status !== '', fn ($query) => $query->where('contact_status', $status))
            ->when($origin !== '', fn ($query) => $query->where('origin', $origin))
            ->orderByRaw("CASE WHEN contact_status = 'pending' THEN 0 ELSE 1 END")
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.contact-requests.index', compact('requests', 'status', 'origin'));
    }

    public function update(Request $request, PriceAlert $contactRequest): RedirectResponse
    {
        $data = $request->validate([
            'contact_status' => ['required', Rule::in(array_keys(PriceAlert::CONTACT_STATUSES))],
        ]);
        $contactRequest->update([
            'contact_status' => $data['contact_status'],
            'contacted_at' => $data['contact_status'] === 'contacted' ? now() : null,
        ]);

        return back()->with('success', 'وضعیت پیگیری شماره به‌روزرسانی شد.');
    }
}
