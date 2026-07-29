@extends('layouts.app')

@section('title', 'ویرایش ' . $tour->title)

@section('content')
    <section class="container admin-page narrow">
        <div class="section-head">
            <div><span class="eyebrow">مدیریت تور</span><h1>{{ $tour->title }}</h1></div>
            <div class="actions"><a href="{{ route('tours.show', $tour) }}" target="_blank">نمایش صفحه</a><a href="{{ route('admin.tours.index') }}">بازگشت</a></div>
        </div>

        <form class="panel admin-form" action="{{ route('admin.tours.update', $tour) }}" method="post" enctype="multipart/form-data">
            @csrf @method('PUT')
            @include('admin.tours._form')
        </form>

        @php($tourImages = collect([$tour->cover_image])->concat($tour->gallery ?? [])->filter()->unique()->values())
        <div class="subsection-head">
            <div><span class="eyebrow">رسانه تور</span><h2>تصاویر و ترتیب نمایش</h2></div>
            <div class="actions">
                <form method="post" action="{{ route('admin.tours.add-images', $tour) }}" onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').textContent='در صف…'">
                    @csrf
                    <button class="button button-secondary">افزودن ۳ عکس خودکار</button>
                </form>
            </div>
        </div>

        @if($tourImages->isNotEmpty())
            <form class="panel image-order-panel" action="{{ route('admin.tours.reorder-images', $tour) }}" method="post" data-image-order>
                @csrf @method('PUT')
                <p>کارت‌ها را بکشید و جابه‌جا کنید یا از دکمه‌های جهت استفاده کنید. تصویر اول، عکس اصلی تور است.</p>
                <div class="admin-image-grid">
                    @foreach($tourImages as $image)
                        <article class="admin-image-card" draggable="true">
                            <input type="hidden" name="images[]" value="{{ $image }}">
                            <img src="{{ Storage::url($image) }}" alt="تصویر {{ $loop->iteration }} تور">
                            <div>
                                <strong class="image-position">{{ $loop->first ? 'عکس اصلی' : 'تصویر '.$loop->iteration }}</strong>
                                <span class="image-move-actions">
                                    <button type="button" data-move="previous" aria-label="انتقال تصویر به قبل">→</button>
                                    <button type="button" data-move="next" aria-label="انتقال تصویر به بعد">←</button>
                                </span>
                            </div>
                        </article>
                    @endforeach
                </div>
                <button class="button" type="submit">ذخیره ترتیب تصاویر</button>
            </form>
        @else
            <div class="panel empty-images">هنوز تصویری برای این تور ثبت نشده است.</div>
        @endif

        <form class="panel admin-form manual-image-form" action="{{ route('admin.tours.upload-images', $tour) }}" method="post" enctype="multipart/form-data">
            @csrf
            <div>
                <h3>افزودن عکس دستی</h3>
                <p>می‌توانید چند عکس انتخاب کنید؛ نخستین فایل انتخاب‌شده به‌صورت پیش‌فرض عکس اول تور می‌شود.</p>
            </div>
            <label>انتخاب تصاویر<input type="file" name="images[]" accept="image/*" multiple required></label>
            <button class="button" type="submit">آپلود و قراردادن در ابتدا</button>
        </form>

        <div class="subsection-head">
            <div><span class="eyebrow">کراولرها</span><h2>منابع مقایسه قیمت</h2></div>
            <div class="actions">
                <form method="post" action="{{ route('admin.sources.official', $tour) }}">@csrf<button class="button button-secondary">افزودن ۱۰ منبع تور</button></form>
                <form method="post" action="{{ route('admin.tours.crawl-content', $tour) }}">@csrf<button class="button button-secondary">بررسی محتوای همه منابع</button></form>
                <form method="post" action="{{ route('admin.tours.crawl', $tour) }}">@csrf<button class="button">به‌روزرسانی قیمت این تور از ۱۰ سایت</button></form>
            </div>
        </div>

        <form class="panel admin-form" action="{{ route('admin.sources.store', $tour) }}" method="post">
            @csrf
            <h3>افزودن منبع جدید</h3>
            @include('admin.sources._fields', ['source' => new \App\Models\PriceSource])
            <button class="button" type="submit">افزودن منبع</button>
        </form>

        <div class="source-stack">
            @foreach ($tour->priceSources as $source)
                <details class="panel source-card" @if($source->last_status === 'failed') open @endif>
                    <summary>
                        <span><strong>{{ $source->provider_name }}</strong><small>{{ $source->extraction_type }}</small></span>
                        <span class="source-summary">
                            @if($source->is_featured)<i class="status featured">پیشنهاد ویژه</i>@endif
                            @if($source->latest_price)<b>{{ number_format($source->latest_price) }} {{ $source->currency }}</b>@endif
                            <i class="status {{ $source->last_status === 'success' || $source->last_status === 'manual' ? 'success' : ($source->last_status === 'failed' ? 'failed' : '') }}">{{ ['success'=>'موفق', 'empty'=>'بدون تور فعال', 'failed'=>'خطا', 'manual'=>'دستی'][$source->last_status] ?? 'بررسی‌نشده' }}</i>
                        </span>
                    </summary>
                    @if($source->last_error)<div class="crawl-error">{{ $source->last_error }}</div>@endif
                    @if($source->content_error)<div class="crawl-error">خطای خواندن محتوا: {{ $source->content_error }}</div>@endif
                    @if($source->content_checked_at)
                        <div class="content-crawl-status">
                            محتوای صفحه {{ $source->content_checked_at->diffForHumans() }} بررسی شد؛
                            {{ count($source->content_insights ?? []) }} موضوع مفید پیدا شد.
                        </div>
                    @endif
                    <form class="admin-form inner-form" action="{{ route('admin.sources.update', $source) }}" method="post">
                        @csrf @method('PUT')
                        @include('admin.sources._fields')
                        <button class="button" type="submit">ذخیره منبع</button>
                    </form>
                    <div class="source-actions">
                        @if($source->extraction_type !== 'manual')<form method="post" action="{{ route('admin.sources.crawl', $source) }}">@csrf<button class="button button-secondary">اجرای آزمایشی</button></form>@endif
                        <form method="post" action="{{ route('admin.agencies.featured') }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="provider_name" value="{{ $source->provider_name }}">
                            <input type="hidden" name="is_featured" value="1">
                            <button class="button button-featured" type="submit">ویژه‌کردن همه پیشنهادهای {{ $source->provider_name }}</button>
                        </form>
                        <form method="post" action="{{ route('admin.agencies.featured') }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="provider_name" value="{{ $source->provider_name }}">
                            <input type="hidden" name="is_featured" value="0">
                            <button class="button button-secondary" type="submit">حذف نشان ویژه از همه</button>
                        </form>
                        <form method="post" action="{{ route('admin.sources.destroy', $source) }}" onsubmit="return confirm('منبع حذف شود؟')">@csrf @method('DELETE')<button class="button button-danger">حذف منبع</button></form>
                    </div>
                </details>
            @endforeach
        </div>
    </section>

    <script>
        (() => {
            const form = document.querySelector('[data-image-order]');
            if (!form) return;

            const grid = form.querySelector('.admin-image-grid');
            let dragged;
            const cards = () => [...grid.querySelectorAll('.admin-image-card')];
            const refreshLabels = () => cards().forEach((card, index) => {
                card.querySelector('.image-position').textContent = index === 0 ? 'عکس اصلی' : `تصویر ${index + 1}`;
            });

            grid.addEventListener('dragstart', event => {
                dragged = event.target.closest('.admin-image-card');
                dragged?.classList.add('is-dragging');
            });
            grid.addEventListener('dragend', () => {
                dragged?.classList.remove('is-dragging');
                dragged = null;
                refreshLabels();
            });
            grid.addEventListener('dragover', event => {
                event.preventDefault();
                const target = event.target.closest('.admin-image-card');
                if (!dragged || !target || target === dragged) return;
                const box = target.getBoundingClientRect();
                grid.insertBefore(dragged, event.clientX < box.left + box.width / 2 ? target.nextSibling : target);
            });
            grid.addEventListener('click', event => {
                const button = event.target.closest('[data-move]');
                if (!button) return;
                const card = button.closest('.admin-image-card');
                if (button.dataset.move === 'previous' && card.previousElementSibling) {
                    grid.insertBefore(card, card.previousElementSibling);
                }
                if (button.dataset.move === 'next' && card.nextElementSibling) {
                    grid.insertBefore(card.nextElementSibling, card);
                }
                refreshLabels();
            });
        })();
    </script>
@endsection
