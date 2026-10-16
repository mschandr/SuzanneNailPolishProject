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
    <header><strong>Nail Polish Collector</strong></header>
    <main>@yield('content')</main>
</body>
</html>
