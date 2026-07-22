@if($advertisements->isNotEmpty())
    <section class="home-ad-slider" aria-label="پیشنهادهای ویژه آژانس‌ها" data-ad-slider>
        <div class="container">
            <div class="ad-slider-head"><span class="eyebrow">پیشنهاد ویژه آژانس‌ها</span><small>تبلیغات</small></div>
            <div class="ad-slider-track">
                @foreach($advertisements as $advertisement)
                    <a class="ad-slide {{ $loop->first ? 'is-active' : '' }}" href="{{ route('advertisements.click', $advertisement) }}" target="_blank" rel="sponsored noopener">
                        @if($advertisement->image_url)<img src="{{ $advertisement->image_url }}" alt="{{ $advertisement->title ?: $advertisement->advertiser_name }}">@endif
                        <div>
                            <small>{{ $advertisement->advertiser_name }}</small>
                            <strong>{{ $advertisement->title ?: $advertisement->name }}</strong>
                            @if($advertisement->subtitle)<span>{{ $advertisement->subtitle }}</span>@endif
                            <b>{{ $advertisement->cta_text }} ←</b>
                        </div>
                    </a>
                @endforeach
            </div>
            @if($advertisements->count() > 1)
                <div class="ad-slider-dots">
                    @foreach($advertisements as $advertisement)<button class="{{ $loop->first ? 'is-active' : '' }}" type="button" aria-label="اسلاید {{ $loop->iteration }}"></button>@endforeach
                </div>
            @endif
        </div>
    </section>
@endif
