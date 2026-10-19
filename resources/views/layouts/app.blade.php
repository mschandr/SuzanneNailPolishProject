<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Nail Polish Collector')</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; color: #222; }
        header { padding: 1rem; background: #7a2e5a; color: #fff; }
        main { max-width: 60rem; margin: 0 auto; padding: 1rem; }
    </style>
</head>
<body>
    <header>
        <strong>Nail Polish Collector</strong>
        <nav style="display:flex;gap:1rem;flex-wrap:wrap;margin-top:.5rem">
            <a href="/catalog" style="color:#fff">Catalog</a>
            @auth
                @if (filled(auth()->user()->display_name))
                    <a href="/collection" style="color:#fff">My collection</a>
                    <a href="/my-polishes" style="color:#fff">My private polishes</a>
                    <a href="/help" style="color:#fff">Help</a>
                    @if (auth()->user()->is_admin)<a href="/admin" style="color:#fff">Admin</a>@endif
                @endif
                <form method="post" action="/logout" style="display:inline">@csrf<button>Sign out</button></form>
            @else
                <a href="/login" style="color:#fff">Sign in</a>
            @endauth
        </nav>
    </header>
    <main>@yield('content')</main>
</body>
</html>
