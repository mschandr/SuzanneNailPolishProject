@php
    $signedIn = auth()->check() && filled(auth()->user()->display_name);
    $here = fn (string ...$patterns) => request()->is(...$patterns) ? 'page' : null;
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#7a2e5a">
    <title>{!! filled($t = trim($__env->yieldContent('title'))) ? $t.' · ' : '' !!}Nail Polish Collector</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/css/site.css?v={{ @filemtime(public_path('css/site.css')) }}">
    <script src="/js/app.js?v={{ @filemtime(public_path('js/app.js')) }}" defer></script>
</head>
<body>
    <a class="skip-link" href="#main">Skip to content</a>
    <header class="site-header">
        <div class="wrap">
            <a class="brand" href="{{ $signedIn ? '/home' : '/' }}"><span aria-hidden="true">💅</span> Nail Polish Collector</a>
            <button type="button" class="menu-button" aria-controls="site-nav" aria-expanded="false">Menu</button>
            <nav id="site-nav" class="nav-links" aria-label="Main">
                <a href="/catalog"@if ($here('catalog*')) aria-current="page"@endif>Catalog</a>
                <a href="/gallery"@if ($here('gallery*')) aria-current="page"@endif>Gallery</a>
                @auth
                    @if ($signedIn)
                        <a href="/collection"@if ($here('collection*')) aria-current="page"@endif>My collection</a>
                        <a href="/manicures"@if ($here('manicures', 'manicures/create', 'manicures/*/edit', 'manicures/*/polishes')) aria-current="page"@endif>My manicures</a>
                        <details class="more">
                            <summary>More</summary>
                            <div class="panel">
                                <a href="/my-polishes"@if ($here('my-polishes*')) aria-current="page"@endif>My private polishes</a>
                                <a href="/liked"@if ($here('liked')) aria-current="page"@endif>Liked</a>
                                <a href="/locations"@if ($here('locations')) aria-current="page"@endif>Locations</a>
                                <a href="/help"@if ($here('help')) aria-current="page"@endif>Help</a>
                            </div>
                        </details>
                    @endif
                    <span class="spacer"></span>
                    @if ($signedIn && auth()->user()->is_admin)<a class="nav-admin" href="/admin"@if ($here('admin*')) aria-current="page"@endif>Admin</a>@endif
                    <form method="post" action="/logout">@csrf<button>Sign out</button></form>
                @else
                    <span class="spacer"></span>
                    <a href="/login"@if ($here('login*')) aria-current="page"@endif>Sign in</a>
                @endauth
            </nav>
        </div>
    </header>
    <main id="main" class="wrap" tabindex="-1">@yield('content')</main>
    <footer class="site-footer">
        <div class="wrap">
            <nav aria-label="Footer">
                <a href="/credits">Photo credits</a>
                <a href="/help">Help</a>
                <a href="/privacy">Privacy</a>
                <a href="/terms">Terms</a>
            </nav>
        </div>
    </footer>
</body>
</html>
