@extends('layouts.app')

@section('title', 'افزودن گروهی منبع مقایسه')

@section('content')
    <section class="container admin-page narrow">
        <div class="section-head">
            <div><span class="eyebrow">مدیریت مقایسه</span><h1>افزودن گروهی منبع</h1></div>
            <a href="{{ route('admin.tours.index') }}">بازگشت به صفحات مقایسه</a>
        </div>

        @if($errors->any())
            <div class="validation-errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <form class="panel admin-form bulk-source-form" action="{{ route('admin.sources.bulk.store') }}" method="post" data-bulk-source-form>
            @csrf
            <datalist id="agency-names">@foreach($agencyNames as $agencyName)<option value="{{ $agencyName }}">@endforeach</datalist>

            <div class="bulk-source-intro">
                <h2>۱. منبع را تعریف کنید</h2>
                <p class="muted">این تنظیمات برای تمام صفحات مقصد یکسان ثبت می‌شود. اگر تنظیم استخراج را خالی بگذارید، کراولرهای مقصد‌محور عنوان هر صفحه را جداگانه استفاده می‌کنند.</p>
            </div>
            @include('admin.sources._fields', ['source' => new \App\Models\PriceSource])

            <fieldset class="bulk-source-targets">
                <legend>۲. صفحات مقصد را انتخاب کنید</legend>
                <div class="bulk-target-mode">
                    <label class="check-label">
                        <input type="radio" name="target_mode" value="category" @checked(old('target_mode', 'category') === 'category')>
                        همه صفحات یک دسته‌بندی
                    </label>
                    <label class="check-label">
                        <input type="radio" name="target_mode" value="selected" @checked(old('target_mode') === 'selected')>
                        فقط صفحات انتخاب‌شده
                    </label>
                </div>

                <div class="bulk-category-target" data-target-panel="category">
                    <label>دسته‌بندی
                        <select name="target_category">
                            @foreach(config('comparison.categories') as $key => $item)
                                @php($count = $tours->where('category', $key)->count())
                                <option value="{{ $key }}" @selected(old('target_category', request('category', 'tour')) === $key)>{{ $item['plural'] }} — {{ $count }} صفحه</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="bulk-selected-target" data-target-panel="selected">
                    <p class="muted">یک یا چند صفحه را از هر دسته انتخاب کنید.</p>
                    <div class="bulk-tour-groups">
                        @foreach(config('comparison.categories') as $key => $item)
                            @php($categoryTours = $tours->where('category', $key))
                            <section class="bulk-tour-group">
                                <header>
                                    <strong>{{ $item['plural'] }}</strong>
                                    <button type="button" class="link-button" data-select-group>انتخاب همه</button>
                                </header>
                                <div class="bulk-tour-options">
                                    @forelse($categoryTours as $tour)
                                        <label class="check-label">
                                            <input type="checkbox" name="tour_ids[]" value="{{ $tour->id }}" @checked(in_array($tour->id, old('tour_ids', [])))>
                                            <span>{{ $tour->title }} @unless($tour->is_active)<small>پیش‌نویس</small>@endunless</span>
                                        </label>
                                    @empty
                                        <span class="muted">صفحه‌ای در این دسته وجود ندارد.</span>
                                    @endforelse
                                </div>
                            </section>
                        @endforeach
                    </div>
                </div>
            </fieldset>

            <div class="bulk-source-submit">
                <p>منبع روی صفحه‌ای که همین نام ارائه‌دهنده را دارد دوباره ساخته نمی‌شود و تنظیم قبلی آن حفظ خواهد شد.</p>
                <button class="button" type="submit">افزودن منبع به صفحات مقصد</button>
            </div>
        </form>
    </section>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.querySelector('[data-bulk-source-form]');
            if (!form) return;

            const refresh = () => {
                const mode = form.querySelector('input[name="target_mode"]:checked')?.value;
                form.querySelectorAll('[data-target-panel]').forEach(panel => {
                    panel.hidden = panel.dataset.targetPanel !== mode;
                    panel.querySelectorAll('input, select').forEach(field => field.disabled = panel.hidden);
                });
            };

            form.addEventListener('change', event => {
                if (event.target.name === 'target_mode') refresh();
            });
            form.querySelectorAll('[data-select-group]').forEach(button => {
                button.addEventListener('click', () => {
                    const boxes = [...button.closest('.bulk-tour-group').querySelectorAll('input[type="checkbox"]')];
                    const shouldCheck = boxes.some(box => !box.checked);
                    boxes.forEach(box => box.checked = shouldCheck);
                    button.textContent = shouldCheck ? 'لغو انتخاب همه' : 'انتخاب همه';
                });
            });
            refresh();
        })();
    </script>
@endpush
