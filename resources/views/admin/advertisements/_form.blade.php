@if($errors->any())
    <div class="validation-errors"><strong>لطفاً خطاهای فرم را اصلاح کنید:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="form-grid">
    <label>نام داخلی کمپین *<input name="name" value="{{ old('name', $advertisement->name) }}" required placeholder="مثلاً کمپین نوروز علی‌بابا"></label>
    <label>نام نمایشی تبلیغ‌دهنده<input name="advertiser_name" value="{{ old('advertiser_name', $advertisement->advertiser_name) }}"><small>اگر آژانسی انتخاب نشود، این فیلد الزامی است.</small></label>
</div>
<label>اتصال به آژانس ثبت‌شده<select name="agency_id"><option value="">بدون اتصال مستقیم</option>@foreach($agencies as $agency)<option value="{{ $agency->id }}" @selected((string) old('agency_id', $advertisement->agency_id) === (string) $agency->id)>{{ $agency->name }}</option>@endforeach</select><small>در صورت انتخاب، نام نمایشی تبلیغ‌دهنده از نام آژانس خوانده می‌شود.</small></label>
<label>جایگاه نمایش *<select name="placement" required>@foreach(\App\Models\Advertisement::PLACEMENTS as $value => $label)<option value="{{ $value }}" @selected(old('placement', $advertisement->placement) === $value)>{{ $label }}</option>@endforeach</select></label>
<div class="form-grid">
    <label>عنوان نمایشی<input name="title" maxlength="180" value="{{ old('title', $advertisement->title) }}" placeholder="تور نوروزی با تخفیف ویژه"></label>
    <label>متن دکمه *<input name="cta_text" maxlength="60" value="{{ old('cta_text', $advertisement->cta_text ?: 'مشاهده پیشنهاد') }}" required></label>
</div>
<label>توضیح کوتاه<textarea name="subtitle" rows="3" maxlength="500">{{ old('subtitle', $advertisement->subtitle) }}</textarea></label>
<div class="form-grid">
    <label>تصویر بنر / اسلاید<input type="file" name="image" accept="image/*">@if($advertisement->image_url)<small><a href="{{ $advertisement->image_url }}" target="_blank">مشاهده تصویر فعلی</a></small>@endif</label>
    <label>لینک مقصد *<input type="url" dir="ltr" name="destination_url" value="{{ old('destination_url', $advertisement->destination_url) }}" required placeholder="https://agency.example/tour"></label>
</div>
<div class="form-grid thirds">
    <label>شروع نمایش<input type="datetime-local" name="starts_at" value="{{ old('starts_at', $advertisement->starts_at?->format('Y-m-d\TH:i')) }}"></label>
    <label>پایان نمایش<input type="datetime-local" name="ends_at" value="{{ old('ends_at', $advertisement->ends_at?->format('Y-m-d\TH:i')) }}"></label>
    <label>اولویت<input type="number" name="priority" min="-1000" max="1000" value="{{ old('priority', $advertisement->priority ?? 0) }}" required><small>عدد بزرگ‌تر زودتر نمایش داده می‌شود.</small></label>
</div>
<div class="form-grid">
    <label>مبلغ قرارداد<input type="number" name="contract_amount" min="0" value="{{ old('contract_amount', $advertisement->contract_amount) }}" placeholder="اختیاری"></label>
    <label>واحد قرارداد<select name="contract_currency"><option @selected(old('contract_currency', $advertisement->contract_currency ?: 'تومان') === 'تومان')>تومان</option><option @selected(old('contract_currency', $advertisement->contract_currency) === 'ریال')>ریال</option></select></label>
</div>
<label class="check-label"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $advertisement->exists ? $advertisement->is_active : true))> تبلیغ فعال باشد</label>
<button class="button" type="submit">ذخیره تبلیغ</button>
