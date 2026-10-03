<div class="form-grid">
    <label>نام سایت *<input list="agency-names" name="provider_name" value="{{ old('provider_name', $source->provider_name) }}" required placeholder="مثلاً علی‌بابا"></label>
    <label>نوع خواندن قیمت
        <select name="extraction_type" required>
            @foreach(['alibaba'=>'علی‌بابا (اختصاصی)', 'flytoday'=>'فلای‌تودی (اختصاصی)', 'safarmarket'=>'سفرمارکت (اختصاصی)', 'marketplace_html'=>'فروشگاه تور HTML (مقصد‌محور)', 'structured'=>'داده ساختاریافته (خودکار)', 'regex'=>'Regex از HTML', 'json'=>'مسیر JSON', 'manual'=>'قیمت دستی'] as $value=>$label)<option value="{{ $value }}" @selected(old('extraction_type', $source->extraction_type ?: 'regex') === $value)>{{ $label }}</option>@endforeach
        </select>
    </label>
</div>
<label>آدرس صفحه منبع *<input type="url" dir="ltr" name="source_url" value="{{ old('source_url', $source->source_url) }}" required placeholder="https://example.com/tour"></label>
<label>لینک خرید <small>(اگر خالی باشد همان آدرس منبع استفاده می‌شود)</small><input type="url" dir="ltr" name="buy_url" value="{{ old('buy_url', $source->buy_url) }}" placeholder="https://example.com/buy"></label>
<label>تنظیم استخراج / نام مقصد
    <textarea name="selector" rows="2" placeholder="برای منابع رسمی خالی بگذارید؛ یا نام مقصد مثل شیراز">{{ old('selector', $source->selector) }}</textarea>
    <small>در منابع رسمی، نام مقصد از عنوان پیشنهاد خوانده می‌شود. برای Regex الگو و برای JSON مسیر نقطه‌ای وارد کنید.</small>
</label>
<div class="form-grid thirds">
    <label>ضریب قیمت<input type="number" step="0.01" min="0.01" name="price_multiplier" value="{{ old('price_multiplier', $source->price_multiplier ?: 1) }}" required></label>
    <label>قیمت دستی<input type="number" min="0" name="latest_price" value="{{ old('latest_price', $source->latest_price) }}"></label>
    <label>واحد نهایی<select name="currency"><option @selected($source->currency !== 'ریال')>تومان</option><option @selected($source->currency === 'ریال')>ریال</option></select></label>
</div>
<label>واحد قیمت در سایت منبع
    <select name="source_currency">
        @foreach(['auto'=>'تشخیص خودکار', 'toman'=>'تومان', 'rial'=>'ریال', 'usd'=>'دلار', 'mixed'=>'ترکیبی دلار و تومان/ریال'] as $value=>$label)
            <option value="{{ $value }}" @selected(old('source_currency', $source->source_currency ?: 'auto') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <small>قیمت دلاری با نرخ دلار آزاد TGJU به تومان تبدیل می‌شود. برای JSON عددیِ بدون واحد، واحد را صریح انتخاب کنید.</small>
</label>
<div class="content-crawl-status">
    تنظیمات عمومی این ارائه‌دهنده مانند شماره تماس، نوع نمایش، اولویت و نشان ویژه از <a href="{{ route('admin.agencies.index') }}">صفحه منابع مرکزی</a> مدیریت می‌شود.
</div>
<label class="check-label"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $source->exists ? $source->is_active : true))> منبع فعال باشد</label>
