@if ($errors->any())
    <div class="validation-errors"><strong>لطفاً خطاهای فرم را اصلاح کنید:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="form-grid">
    <label>نام تور *<input name="title" value="{{ old('title', $tour->title) }}" required placeholder="مثلاً تور شیراز"></label>
    <label>آدرس انگلیسی<input name="slug" dir="ltr" value="{{ old('slug', $tour->slug) }}" placeholder="shiraz-tour"></label>
</div>
<label>توضیح کوتاه<textarea name="excerpt" rows="2" maxlength="300" placeholder="متنی که روی کارت تور نمایش داده می‌شود">{{ old('excerpt', $tour->excerpt) }}</textarea></label>
<label>متن کامل تور *<textarea name="description" rows="9" required>{{ old('description', $tour->description) }}</textarea></label>
<div class="form-grid">
    <label>عکس اصلی<input type="file" name="cover_image" accept="image/*"></label>
    <label>تصاویر گالری<input type="file" name="gallery[]" accept="image/*" multiple></label>
</div>
<label>لینک ویدئو<input type="url" name="video_url" dir="ltr" value="{{ old('video_url', $tour->video_url) }}" placeholder="https://..."></label>
<label class="check-label"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $tour->exists ? $tour->is_active : true))> نمایش تور در سایت</label>
<button class="button" type="submit">ذخیره تور</button>
