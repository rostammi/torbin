@php($googleTagManagerId = strtoupper(trim((string) config('services.google_tag_manager.container_id'))))
@if(preg_match('/\AGTM-[A-Z0-9]+\z/', $googleTagManagerId))
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $googleTagManagerId }}"
    height="0" width="0" style="display:none;visibility:hidden" title="Google Tag Manager"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
@endif
