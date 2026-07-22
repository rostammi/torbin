<aside class="ad-banner {{ $class ?? '' }}" aria-label="تبلیغات">
    <a href="{{ route('advertisements.click', $advertisement) }}" target="_blank" rel="sponsored noopener">
        @if($advertisement->image_url)
            <img src="{{ $advertisement->image_url }}" alt="{{ $advertisement->title ?: $advertisement->advertiser_name }}">
        @endif
        <div class="ad-copy">
            <small>تبلیغات · {{ $advertisement->advertiser_name }}</small>
            @if($advertisement->title)<strong>{{ $advertisement->title }}</strong>@endif
            @if($advertisement->subtitle)<span>{{ $advertisement->subtitle }}</span>@endif
        </div>
        <b>{{ $advertisement->cta_text }} ←</b>
    </a>
</aside>
