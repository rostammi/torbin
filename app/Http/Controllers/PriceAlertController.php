<?php

namespace App\Http\Controllers;

use App\Models\PriceAlert;
use App\Models\Tour;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PriceAlertController extends Controller
{
    public function store(Request $request, Tour $tour): RedirectResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'consent' => ['accepted'],
        ], [
            'phone.required' => 'شماره موبایل را وارد کنید.',
            'consent.accepted' => 'برای ثبت هشدار باید با دریافت پیامک موافقت کنید.',
        ]);

        $phone = $this->normalizePhone($request->string('phone')->toString());
        if (! preg_match('/^09\d{9}$/', $phone)) {
            throw ValidationException::withMessages(['phone' => 'شماره موبایل ایرانی معتبر وارد کنید.']);
        }

        $offer = $tour->priceSources()
            ->where('is_active', true)
            ->funded()
            ->where('latest_price', '>', 0)
            ->orderBy('latest_price')
            ->first();

        $origin = $offer ? 'price_drop' : 'no_price_contact';
        if (! $offer && ! $tour->priceSources()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('latest_price')->orWhere('latest_price', '<=', 0))
            ->exists()) {
            return back()->with('error', 'در حال حاضر پیشنهادی برای ثبت درخواست تماس وجود ندارد.')->withInput();
        }

        $phoneHash = hash_hmac('sha256', $phone, config('app.key'));
        $alert = PriceAlert::firstOrNew([
            'tour_id' => $tour->id,
            'phone_hash' => $phoneHash,
            'origin' => $origin,
        ]);

        if (! $alert->exists) {
            $token = Str::random(48);
            $alert->unsubscribe_token = $token;
            $alert->unsubscribe_token_hash = hash('sha256', $token);
        }

        $alert->fill([
            'phone' => $phone,
            'target_price' => $offer?->latest_price,
            'currency' => $offer?->currency ?? 'تومان',
            'is_active' => $offer !== null,
            'contact_status' => 'pending',
            'contacted_at' => null,
            'last_error' => null,
        ])->save();

        return back()->with('success', $offer
            ? 'هشدار کاهش قیمت فعال شد؛ اگر قیمت از مبلغ فعلی کمتر شود به شما پیام می‌دهیم.'
            : 'شماره شما ثبت شد؛ برای اعلام قیمت و راهنمایی با شما تماس می‌گیریم.');
    }

    public function unsubscribe(string $token): RedirectResponse
    {
        $alert = PriceAlert::query()
            ->where('unsubscribe_token_hash', hash('sha256', $token))
            ->firstOrFail();

        $alert->update(['is_active' => false]);

        return redirect()->to($alert->tour->publicUrl())->with('success', 'هشدار کاهش قیمت لغو شد.');
    }

    private function normalizePhone(string $phone): string
    {
        $phone = str_replace(
            ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $phone,
        );
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '0098')) {
            return '0'.substr($digits, 4);
        }
        if (str_starts_with($digits, '98') && strlen($digits) === 12) {
            return '0'.substr($digits, 2);
        }
        if (str_starts_with($digits, '9') && strlen($digits) === 10) {
            return '0'.$digits;
        }

        return $digits;
    }
}
