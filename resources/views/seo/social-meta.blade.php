@php
    $socialTitle = trim($__env->yieldContent('title', 'گیت | مقایسه قیمت تور، هتل، اقامتگاه و خدمات ویزا'));
    $pageMeta = $__env->yieldContent('meta');

    preg_match('/<meta\s+name=["\']description["\']\s+content=["\']([^"\']*)["\']/i', $pageMeta, $descriptionMatch);
    preg_match('/<link\s+rel=["\']canonical["\']\s+href=["\']([^"\']*)["\']/i', $pageMeta, $canonicalMatch);

    $socialDescription = html_entity_decode(
        $descriptionMatch[1] ?? 'در گیت قیمت تور، هتل، اقامتگاه و خدمات ویزا را از ارائه‌دهندگان معتبر مقایسه کنید و بهترین پیشنهاد را پیدا کنید.',
        ENT_QUOTES | ENT_HTML5,
        'UTF-8',
    );
    $socialUrl = html_entity_decode($canonicalMatch[1] ?? url()->current(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $socialImage = asset('images/geyt-social-share.png');
    $socialImageAlt = 'گیت؛ مرجع مقایسه قیمت خدمات سفر';
    $usesDefaultSocialImage = true;

    $absoluteSocialImage = static function (string $image): string {
        if (str_starts_with($image, '//')) {
            return 'https:'.$image;
        }

        if (preg_match('~^https?://~i', $image)) {
            return $image;
        }

        return url('/'.ltrim($image, '/'));
    };

    if (isset($tour) && $tour instanceof \App\Models\Tour && $tour->cover_image) {
        $socialImage = $absoluteSocialImage(Storage::url($tour->cover_image));
        $socialImageAlt = 'تصویر اصلی '.$tour->title;
        $usesDefaultSocialImage = false;
    } elseif (isset($page) && $page instanceof \App\Models\StaticPage && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $page->content, $contentImage)) {
        $socialImage = $absoluteSocialImage(html_entity_decode($contentImage[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $socialImageAlt = 'تصویر مطلب '.$page->title;
        $usesDefaultSocialImage = false;
    }

    $socialType = request()->routeIs('mag.*') && ! request()->routeIs('mag.index') ? 'article' : 'website';
@endphp

<meta property="og:locale" content="fa_IR">
<meta property="og:site_name" content="گیت">
<meta property="og:type" content="{{ $socialType }}">
<meta property="og:title" content="{{ $socialTitle }}">
<meta property="og:description" content="{{ $socialDescription }}">
<meta property="og:url" content="{{ $socialUrl }}">
<meta property="og:image" content="{{ $socialImage }}">
<meta property="og:image:secure_url" content="{{ $socialImage }}">
<meta property="og:image:alt" content="{{ $socialImageAlt }}">
@if($usesDefaultSocialImage)
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
@endif

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $socialTitle }}">
<meta name="twitter:description" content="{{ $socialDescription }}">
<meta name="twitter:image" content="{{ $socialImage }}">
<meta name="twitter:image:alt" content="{{ $socialImageAlt }}">
