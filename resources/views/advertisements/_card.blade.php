<article class="tour-card ad-result-card" aria-label="تبلیغات {{ $advertisement->advertiser_name }}">
    <a href="{{ route('advertisements.click', $advertisement) }}" target="_blank" rel="sponsored noopener">
        <div class="ad-result-image">
            @if($advertisement->image_url)
                <img src="{{ $advertisement->image_url }}" alt="{{ $advertisement->title ?: $advertisement->advertiser_name }}">
            @else
                <span>✈</span>
            @endif
            <small>تبلیغات</small>
        </div>
        <div class="card-body">
            <span class="eyebrow">{{ $advertisement->advertiser_name }}</span>
            <h3>{{ $advertisement->title ?: $advertisement->name }}</h3>
            <p>{{ $advertisement->subtitle }}</p>
            <b class="ad-card-cta">{{ $advertisement->cta_text }} ←</b>
        </div>
    </a>
</article>
