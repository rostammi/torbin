<div class="form-grid">
    <label>نام سایت *<input name="provider_name" value="{{ old('provider_name', $source->provider_name) }}" required placeholder="مثلاً علی‌بابا"></label>
    <label>نوع خواندن قیمت
        <select name="extraction_type" required>
            @foreach(['alibaba'=>'علی‌بابا (اختصاصی)', 'flytoday'=>'فلای‌تودی (اختصاصی)', 'safarmarket'=>'سفرمارکت (اختصاصی)', 'structured'=>'داده ساختاریافته (خودکار)', 'regex'=>'Regex از HTML', 'json'=>'مسیر JSON', 'manual'=>'قیمت دستی'] as $value=>$label)<option value="{{ $value }}" @selected(old('extraction_type', $source->extraction_type ?: 'regex') === $value)>{{ $label }}</option>@endforeach
        </select>
    </label>
</div>
<label>آدرس صفحه منبع *<input type="url" dir="ltr" name="source_url" value="{{ old('source_url', $source->source_url) }}" required placeholder="https://example.com/tour"></label>
<label>لینک خرید <small>(اگر خالی باشد همان آدرس منبع استفاده می‌شود)</small><input type="url" dir="ltr" name="buy_url" value="{{ old('buy_url', $source->buy_url) }}" placeholder="https://example.com/buy"></label>
<label>تنظیم استخراج / نام مقصد
    <textarea name="selector" rows="2" placeholder="برای منابع رسمی خالی بگذارید؛ یا نام مقصد مثل شیراز">{{ old('selector', $source->selector) }}</textarea>
    <small>در منابع رسمی، نام مقصد از عنوان تور خوانده می‌شود. برای Regex الگو و برای JSON مسیر نقطه‌ای وارد کنید.</small>
</label>
<div class="form-grid thirds">
    <label>ضریب قیمت<input type="number" step="0.01" min="0.01" name="price_multiplier" value="{{ old('price_multiplier', $source->price_multiplier ?: 1) }}" required></label>
    <label>قیمت دستی<input type="number" min="0" name="latest_price" value="{{ old('latest_price', $source->latest_price) }}"></label>
    <label>واحد<select name="currency"><option @selected($source->currency !== 'ریال')>تومان</option><option @selected($source->currency === 'ریال')>ریال</option></select></label>
</div>
<label class="check-label"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $source->exists ? $source->is_active : true))> منبع فعال باشد</label>
<label class="check-label"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $source->is_featured))> این پیشنهاد «پیشنهاد ویژه» باشد</label>
