<nav class="admin-nav" aria-label="Admin">
    @foreach ([
        '/admin' => ['Dashboard', ['admin']],
        '/admin/polishes' => ['Polishes', ['admin/polishes', 'admin/polishes/*/edit']],
        '/admin/polishes/create' => ['Add a polish', ['admin/polishes/create']],
        '/admin/imports' => ['Import', ['admin/imports*']],
        '/admin/needs-review' => ['Needs review', ['admin/needs-review']],
        '/admin/moderation' => ['Moderation', ['admin/moderation*']],
        '/admin/suggestions' => ['Suggestions', ['admin/suggestions']],
        '/admin/invites' => ['Invites', ['admin/invites']],
        '/admin/users' => ['Admins', ['admin/users']],
        '/admin/finishes' => ['Finishes', ['admin/finishes']],
        '/admin/color-families' => ['Color families', ['admin/color-families']],
    ] as $href => [$label, $patterns])
        <a href="{{ $href }}" @if (request()->is(...$patterns)) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
