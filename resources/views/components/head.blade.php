@props(['title' => null])
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
<meta name="description" content="StoreSiphon turns public WooCommerce catalogues into clean, structured product data.">
<link rel="icon" href="/favicon.ico" sizes="48x48">
<link rel="icon" href="/brand/icons/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/brand/icons/apple-touch-icon.png">
<link rel="manifest" href="/brand/icons/site.webmanifest">
<meta name="theme-color" content="#0B1F2E">
{{-- Resolve the theme before first paint (mirrors resources/js/theme.js) --}}
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    (function () {
        var p = 'system';
        try { p = localStorage.getItem('ss-theme') || 'system'; } catch (e) {}
        if (p !== 'light' && p !== 'dark') p = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        document.documentElement.dataset.theme = p;
    })();
</script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
