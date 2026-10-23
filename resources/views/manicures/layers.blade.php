@extends('layouts.app')

@section('title', 'Polishes worn')

@section('content')
    @php($nailsMode = $manicure->polish_mode === \App\Models\Manicure::MODE_NAILS)
    <p class="breadcrumb"><a href="/manicures/{{ $manicure->id }}">Back to the manicure</a></p>
    <h1>Polishes worn</h1>
    @if (session('status'))<p role="status">{{ session('status') }}</p>@endif
    @foreach (['polish_id', 'bottle_id', 'direction', 'target', 'mode', 'copy'] as $field)@error($field)<p role="alert">{{ $message }}</p>@enderror @endforeach

    <form method="post" action="/manicures/{{ $manicure->id }}/polish-mode" class="card">
        @csrf @method('put')
        @if ($nailsMode)
            <input type="hidden" name="mode" value="hand">
            <button class="btn-sm">Switch to whole-hand mode</button>
            <small class="hint">Your per-nail lists are kept and come back if you switch again.</small>
        @else
            <input type="hidden" name="mode" value="nails">
            <button class="btn-sm">Switch to per-nail mode</button>
            <small class="hint">For accent nails and nail art: give each of the 10 nails its own list. Your whole-hand list is kept.</small>
        @endif
    </form>

    @if ($nailsMode)
        <nav aria-label="Nails" class="nail-picker">
            @foreach (\App\Models\Manicure::NAILS as $key => $label)
                <a href="/manicures/{{ $manicure->id }}/polishes?nail={{ $key }}" @if ($key === $target) aria-current="page" @endif>
                    {{ $label }}@if (($nailCounts[$key] ?? 0) > 0) ({{ $nailCounts[$key] }})@endif
                </a>
            @endforeach
        </nav>
        @if ($handCount > 0)
            <form method="post" action="/manicures/{{ $manicure->id }}/polishes/copy-to-nails" class="card"
                  onsubmit="return confirm('Replace every nail\'s list with the whole-hand list?')">@csrf
                <button class="btn-sm">Copy the whole-hand list onto every nail</button>
                <small class="hint">Handy for accent nails: copy, then change just the one or two that differ.</small>
            </form>
        @endif
        <h2>{{ \App\Models\Manicure::NAILS[$target] }}</h2>
    @endif

    <p>List them in the order you applied them, base coat first. You can add polishes you don't own, and the same polish twice for two coats.</p>

    <ol class="layers">
        @forelse ($layers as $layer)
            <li>
                <div class="polish-line">
                    @if ($src = $layer->polish->swatchSrc())<img class="swatch" src="{{ $src }}" alt="" loading="lazy" referrerpolicy="no-referrer">@endif
                    <span><strong>{{ $layer->polish->brand->name }}</strong> {{ $layer->polish->name }}
                    @if ($layer->polish->isPrivate())<small>(private polish)</small>@endif</span>
                </div>
                <div class="row mt-sm">
                <form method="post" action="/manicure-polishes/{{ $layer->id }}/move" class="inline">@csrf
                    <button name="direction" value="up" class="btn-sm" @disabled($loop->first)>Up</button>
                    <button name="direction" value="down" class="btn-sm" @disabled($loop->last)>Down</button>
                </form>
                @if (($bottles[$layer->polish_id] ?? collect())->isNotEmpty())
                    <form method="post" action="/manicure-polishes/{{ $layer->id }}" class="inline">@csrf @method('put')
                        <select name="bottle_id" aria-label="My bottle" class="auto-w">
                            <option value="">Not linked to a bottle</option>
                            @foreach ($bottles[$layer->polish_id] as $bottle)
                                <option value="{{ $bottle->id }}" @selected($layer->bottle_id === $bottle->id)>{{ $bottle->location?->name ?? 'No location' }}@if ($bottle->status !== 'owned') ({{ Str::headline($bottle->status) }})@endif</option>
                            @endforeach
                        </select>
                        <button class="btn-sm">Link</button>
                    </form>
                @endif
                <form method="post" action="/manicure-polishes/{{ $layer->id }}" class="inline">@csrf @method('delete')<button class="btn-danger btn-sm">Remove</button></form>
                </div>
            </li>
        @empty
            <li class="no-bullet">Nothing listed yet.</li>
        @endforelse
    </ol>

    <h2>Add a polish{{ $nailsMode ? ' to the '.strtolower(\App\Models\Manicure::NAILS[$target]) : '' }}</h2>
    @if ($full)
        <p>This list has the maximum of {{ \App\Models\ManicurePolish::MAX_LAYERS }} layers.</p>
    @else
        <p class="flash-warn flash"><small>Heads up: if you add one of your private polishes to a public manicure, its name will be visible to everyone who can see the manicure. The polish itself stays private.</small></p>
        <form method="get" action="/manicures/{{ $manicure->id }}/polishes">
            @if ($nailsMode)<input type="hidden" name="nail" value="{{ $target }}">@endif
            <div class="form-row">
                <div class="field grow-field"><label for="q">Search by shade or brand</label><input id="q" type="search" name="q" value="{{ $q }}" autofocus class="full"></div>
                <button class="btn-primary">Search</button>
            </div>
        </form>

        @if ($q !== '')
            <ul class="list mt-md">
                @forelse ($results as $polish)
                    <li>
                        <div class="polish-line">
                            @if ($src = $polish->swatchSrc())<img class="swatch" src="{{ $src }}" alt="" loading="lazy" referrerpolicy="no-referrer">@endif
                            <span><strong>{{ $polish->brand->name }}</strong> {{ $polish->name }}
                            @if ($polish->isPrivate())<small>(private polish)</small>@endif</span>
                        </div>
                        <form method="post" action="/manicures/{{ $manicure->id }}/polishes" class="row mt-sm">@csrf
                            <input type="hidden" name="polish_id" value="{{ $polish->id }}">
                            @if ($nailsMode)<input type="hidden" name="target" value="{{ $target }}">@endif
                            @if (($bottles[$polish->id] ?? collect())->isNotEmpty())
                                <select name="bottle_id" aria-label="My bottle" class="auto-w">
                                    <option value="">Not linked to a bottle</option>
                                    @foreach ($bottles[$polish->id] as $bottle)
                                        <option value="{{ $bottle->id }}">My bottle: {{ $bottle->location?->name ?? 'No location' }}@if ($bottle->status !== 'owned') ({{ Str::headline($bottle->status) }})@endif</option>
                                    @endforeach
                                </select>
                            @endif
                            <button class="btn-primary btn-sm">Add</button>
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
