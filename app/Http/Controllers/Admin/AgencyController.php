<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\SiteSetting;
use App\Services\Billing\AgencyBillingService;
use App\Support\AdminTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AgencyController extends Controller
{
    public function index(Request $request): View
    {
        $term = AdminTable::term($request);
        $query = Agency::query()
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
            ->when($term !== '', fn ($query) => $query->where(fn ($search) => $search
                ->where('name', 'like', "%{$term}%")
                ->orWhere('contact_phone', 'like', "%{$term}%")
                ->orWhereHas('users', fn ($users) => $users->where('email', 'like', "%{$term}%"))));
        AdminTable::sort($query, $request, [
            'name' => 'name', 'balance' => 'balance', 'sources' => 'price_sources_count',
            'clicks' => 'clicks_count', 'charged' => 'total_charged', 'cost' => 'cost_per_click',
        ], [['name', 'asc']]);
        $agencies = $query->paginate(20)->withQueryString();

        $comparisonContactPhone = SiteSetting::comparisonContactPhone();
        $comparisonContactPhones = collect(config('comparison.categories'))
            ->mapWithKeys(fn ($item, $key) => [$key => SiteSetting::comparisonContactPhone($key)]);
        $contactOrderAgencies = Agency::query()
            ->whereHas('priceSources')
            ->withCount('priceSources')
            ->orderBy('contact_priority')
            ->orderBy('name')
            ->get();

        return view('admin.agencies.index', compact('agencies', 'comparisonContactPhone', 'comparisonContactPhones', 'contactOrderAgencies'));
    }

    public function update(Request $request, Agency $agency): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120', Rule::unique('agencies', 'name')->ignore($agency)],
            'cost_per_click' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'contact_priority' => ['sometimes', 'required', 'integer', 'min:0', 'max:100000'],
            'display_priority' => ['sometimes', 'required', 'integer', 'min:0', 'max:10000'],
            'contact_phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9۰-۹٠-٩+()\s-]+$/u'],
            'is_featured' => ['nullable', 'boolean'],
            'is_contact_only' => ['nullable', 'boolean'],
            'is_pinned' => ['nullable', 'boolean'],
        ], [
            'contact_phone.regex' => 'شماره تماس فقط می‌تواند شامل رقم، فاصله، خط تیره، پرانتز و علامت + باشد.',
        ]);
        $data['name'] = trim($data['name'] ?? $agency->name);
        $data['contact_priority'] = $data['contact_priority'] ?? $agency->contact_priority;
        $data['display_priority'] = $data['display_priority'] ?? $agency->display_priority;
        $data['contact_phone'] = trim((string) ($data['contact_phone'] ?? '')) ?: null;
        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_contact_only'] = $request->boolean('is_contact_only');
        $data['is_pinned'] = $request->boolean('is_pinned');
        if ($data['is_pinned']) {
            $data['is_featured'] = true;
        }

        DB::transaction(function () use ($agency, $data) {
            $oldName = $agency->name;
            if ($data['is_pinned']) {
                Agency::query()->whereKeyNot($agency->id)->update(['is_pinned' => false]);
            }
            $agency->update($data);
            if ($oldName !== $agency->name) {
                $agency->priceSources()->update(['provider_name' => $agency->name]);
            }
        });

        return back()->with('success', "تنظیمات عمومی منبع {$agency->name} روی همه پیشنهادهایش اعمال شد.");
    }

    public function updateComparisonContact(Request $request): RedirectResponse
    {
        if ($request->has('comparison_contact_phones')) {
            $categoryRules = collect(array_keys(config('comparison.categories')))
                ->mapWithKeys(fn ($category) => ["comparison_contact_phones.{$category}" => [
                    'required', 'string', 'min:7', 'max:30', 'regex:/^[0-9۰-۹٠-٩+()\s-]+$/u',
                ]])
                ->all();
            $data = $request->validate([
                'comparison_contact_phones' => ['required', 'array'],
                ...$categoryRules,
            ], [
                'comparison_contact_phones.*.regex' => 'شماره تماس فقط می‌تواند شامل رقم، فاصله، خط تیره، پرانتز و علامت + باشد.',
            ]);

            DB::transaction(function () use ($data) {
                foreach ($data['comparison_contact_phones'] as $category => $phone) {
                    SiteSetting::query()->updateOrCreate(
                        ['key' => SiteSetting::contactPhoneKey($category)],
                        ['value' => trim($phone)],
                    );
                }
            });

            return back()->with('success', 'شماره تماس هر دسته‌بندی ذخیره شد.');
        }

        $data = $request->validate([
            'comparison_contact_phone' => ['required', 'string', 'min:7', 'max:30', 'regex:/^[0-9۰-۹٠-٩+()\s-]+$/u'],
        ], [
            'comparison_contact_phone.regex' => 'شماره تماس فقط می‌تواند شامل رقم، فاصله، خط تیره، پرانتز و علامت + باشد.',
        ]);

        SiteSetting::query()->updateOrCreate(
            ['key' => SiteSetting::CONTACT_PHONE],
            ['value' => trim($data['comparison_contact_phone'])],
        );

        return back()->with('success', 'شماره تماس پیشنهادهای بدون قیمت ذخیره شد.');
    }

    public function updateContactOrder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'agencies' => ['required', 'array', 'min:1'],
            'agencies.*' => ['required', 'integer', 'distinct', Rule::exists('agencies', 'id')],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['agencies'] as $position => $agencyId) {
                Agency::query()->whereKey($agencyId)->update([
                    'contact_priority' => ($position + 1) * 10,
                ]);
            }
        });

        return back()->with('success', 'ترتیب انتخاب منبع «تماس بگیرید» ذخیره شد.');
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
