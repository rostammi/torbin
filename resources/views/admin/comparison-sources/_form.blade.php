<div class="form-grid">
    <label>نام منبع *
        <input name="name" value="{{ old('name', $source->name) }}" required maxlength="120" placeholder="مثلاً سایت آژانس نمونه">
    </label>
    <label>آدرس هوم‌پیج *
        <input type="url" dir="ltr" name="homepage_url" value="{{ old('homepage_url', $source->homepage_url) }}" required maxlength="2000" placeholder="https://example.com">
    </label>
</div>

<fieldset class="panel">
    <legend>دسته‌هایی که باید در این منبع بررسی شوند *</legend>
    <div class="form-grid">
        @foreach($categories as $key => $category)
            <label class="check-label">
                <input type="checkbox" name="categories[]" value="{{ $key }}" @checked(in_array($key, old('categories', $source->categories ?? []), true))>
                {{ $category['label'] }}
            </label>
        @endforeach
    </div>
    @error('categories')<small class="field-error">{{ $message }}</small>@enderror
</fieldset>

<label class="check-label">
    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $source->exists ? $source->is_active : true))>
    منبع فعال و قابل اسکن باشد
</label>

<button class="button" type="submit">ذخیره منبع</button>
