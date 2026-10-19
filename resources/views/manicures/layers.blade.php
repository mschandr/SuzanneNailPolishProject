@extends('layouts.app')

@section('title', 'Polishes worn')

@section('content')
    @php($nailsMode = $manicure->polish_mode === \App\Models\Manicure::MODE_NAILS)
    <p><a href="/manicures/{{ $manicure->id }}">Back to the manicure</a></p>
    <h1>Polishes worn</h1>
    @if (session('status'))<p role="status">{{ session('status') }}</p>@endif
    @foreach (['polish_id', 'bottle_id', 'direction', 'target', 'mode', 'copy'] as $field)@error($field)<p role="alert">{{ $message }}</p>@enderror @endforeach

    <form method="post" action="/manicures/{{ $manicure->id }}/polish-mode">
        @csrf @method('put')
        @if ($nailsMode)
            <input type="hidden" name="mode" value="hand">
            <button>Switch to whole-hand mode</button>
            <small>Your per-nail lists are kept and come back if you switch again.</small>
        @else
            <input type="hidden" name="mode" value="nails">
            <button>Switch to per-nail mode</button>
            <small>For accent nails and nail art: give each of the 10 nails its own list. Your whole-hand list is kept.</small>
        @endif
    </form>

    @if ($nailsMode)
        <nav aria-label="Nails" style="display:grid;grid-template-columns:repeat(5,1fr);gap:.25rem;margin:1rem 0">
            @foreach (\App\Models\Manicure::NAILS as $key => $label)
                <a href="/manicures/{{ $manicure->id }}/polishes?nail={{ $key }}"
                   @if ($key === $target) aria-current="page" style="font-weight:bold;border:2px solid #7a2e5a;padding:.25rem" @else style="padding:.25rem;border:1px solid #ccc" @endif>
                    {{ $label }}@if (($nailCounts[$key] ?? 0) > 0) ({{ $nailCounts[$key] }})@endif
                </a>
            @endforeach
        </nav>
        @if ($handCount > 0)
            <form method="post" action="/manicures/{{ $manicure->id }}/polishes/copy-to-nails"
                  onsubmit="return confirm('Replace every nail\'s list with the whole-hand list?')">@csrf
                <button>Copy the whole-hand list onto every nail</button>
                <small>Handy for accent nails: copy, then change just the one or two that differ.</small>
            </form>
        @endif
        <h2>{{ \App\Models\Manicure::NAILS[$target] }}</h2>
    @endif

    <p>List them in the order you applied them, base coat first. You can add polishes you don't own, and the same polish twice for two coats.</p>

    <ol>
        @forelse ($layers as $layer)
            <li>
                <strong>{{ $layer->polish->brand->name }}</strong> {{ $layer->polish->name }}
                @if ($layer->polish->isPrivate())<small>(private polish)</small>@endif
                <form method="post" action="/manicure-polishes/{{ $layer->id }}/move" style="display:inline">@csrf
                    <button name="direction" value="up" @disabled($loop->first)>Up</button>
                    <button name="direction" value="down" @disabled($loop->last)>Down</button>
                </form>
                @if (($bottles[$layer->polish_id] ?? collect())->isNotEmpty())
                    <form method="post" action="/manicure-polishes/{{ $layer->id }}" style="display:inline">@csrf @method('put')
                        <select name="bottle_id" aria-label="My bottle">
                            <option value="">Not linked to a bottle</option>
                            @foreach ($bottles[$layer->polish_id] as $bottle)
                                <option value="{{ $bottle->id }}" @selected($layer->bottle_id === $bottle->id)>{{ $bottle->location?->name ?? 'No location' }}@if ($bottle->status !== 'owned') ({{ Str::headline($bottle->status) }})@endif</option>
                            @endforeach
                        </select>
                        <button>Link</button>
                    </form>
                @endif
                <form method="post" action="/manicure-polishes/{{ $layer->id }}" style="display:inline">@csrf @method('delete')<button>Remove</button></form>
            </li>
        @empty
            <li>Nothing listed yet.</li>
        @endforelse
    </ol>

    <h2>Add a polish{{ $nailsMode ? ' to the '.strtolower(\App\Models\Manicure::NAILS[$target]) : '' }}</h2>
    @if ($full)
        <p>This list has the maximum of {{ \App\Models\ManicurePolish::MAX_LAYERS }} layers.</p>
    @else
        <p><small>Heads up: if you add one of your private polishes to a public manicure, its name will be visible to everyone who can see the manicure. The polish itself stays private.</small></p>
        <form method="get" action="/manicures/{{ $manicure->id }}/polishes">
            @if ($nailsMode)<input type="hidden" name="nail" value="{{ $target }}">@endif
            <label>Search by shade or brand <input type="search" name="q" value="{{ $q }}" autofocus></label>
            <button>Search</button>
        </form>

        @if ($q !== '')
            <ul>
                @forelse ($results as $polish)
                    <li>
                        <strong>{{ $polish->brand->name }}</strong> {{ $polish->name }}
                        @if ($polish->isPrivate())<small>(private polish)</small>@endif
                        <form method="post" action="/manicures/{{ $manicure->id }}/polishes" style="display:inline">@csrf
                            <input type="hidden" name="polish_id" value="{{ $polish->id }}">
                            @if ($nailsMode)<input type="hidden" name="target" value="{{ $target }}">@endif
                            @if (($bottles[$polish->id] ?? collect())->isNotEmpty())
                                <select name="bottle_id" aria-label="My bottle">
                                    <option value="">Not linked to a bottle</option>
                                    @foreach ($bottles[$polish->id] as $bottle)
                                        <option value="{{ $bottle->id }}">My bottle: {{ $bottle->location?->name ?? 'No location' }}@if ($bottle->status !== 'owned') ({{ Str::headline($bottle->status) }})@endif</option>
                                    @endforeach
                                </select>
                            @endif
                            <button>Add</button>
                        </form>
                    </li>
                @empty
                    <li>No matches. Not in the Catalog? <a href="/my-polishes/create">Add it as a private polish</a>.</li>
                @endforelse
            </ul>
            @if ($truncated)<p>Showing the first {{ $limit }} matches. Search for something more specific to narrow it down.</p>@endif
        @endif
    @endif
@endsection
