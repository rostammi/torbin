<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Services\Billing\AgencyBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AgencyController extends Controller
{
    public function index(): View
    {
        $agencies = Agency::query()
            ->with([
                'creditTransactions' => fn ($query) => $query->latest()->limit(5),
                'users' => fn ($query) => $query->where('role', 'agency')->oldest(),
            ])
            ->withCount([
                'priceSources',
                'clicks',
                'clicks as charged_clicks_count' => fn ($query) => $query->where('status', 'charged'),
            ])
            ->withSum('clicks as total_charged', 'charged_amount')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.agencies.index', compact('agencies'));
    }

    public function update(Request $request, Agency $agency): RedirectResponse
    {
        $data = $request->validate([
            'cost_per_click' => ['required', 'integer', 'min:0', 'max:1000000000'],
        ]);
        $agency->update($data);

        return back()->with('success', "هزینه هر کلیک {$agency->name} ذخیره شد.");
    }

    public function adjustBalance(Request $request, Agency $agency, AgencyBillingService $billing): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['credit', 'debit'])],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000000'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $billing->adjustBalance($agency, $data['amount'], $data['type'], $data['note'] ?? null, $request->user());

        return back()->with('success', 'موجودی آژانس و دفتر تراکنش‌ها به‌روزرسانی شد.');
    }

    public function saveAccess(Request $request, Agency $agency): RedirectResponse
    {
        $account = $agency->users()->where('role', 'agency')->first();
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($account)],
            'password' => [$account ? 'nullable' : 'required', 'nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $values = [
            'name' => $agency->name,
            'email' => $data['email'],
            'role' => 'agency',
            'agency_id' => $agency->id,
        ];
        if (! empty($data['password'])) {
            $values['password'] = $data['password'];
        }

        $account ? $account->update($values) : $agency->users()->create($values);

        return back()->with('success', "دسترسی داشبورد {$agency->name} ذخیره شد.");
    }
}
