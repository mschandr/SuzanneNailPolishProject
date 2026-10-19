@extends('layouts.app')

@section('title', 'Polishes worn')

@section('content')
    <p><a href="/manicures/{{ $manicure->id }}">Back to the manicure</a></p>
    <h1>Polishes worn</h1>
    @if (session('status'))<p role="status">{{ session('status') }}</p>@endif
    @foreach (['polish_id', 'bottle_id', 'direction'] as $field)@error($field)<p role="alert">{{ $message }}</p>@enderror @endforeach

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

    <h2>Add a polish</h2>
    @if ($full)
        <p>This manicure has the maximum of {{ \App\Models\ManicurePolish::MAX_LAYERS }} layers.</p>
    @else
        <p><small>Heads up: if you add one of your private polishes to a public manicure, its name will be visible to everyone who can see the manicure. The polish itself stays private.</small></p>
        <form method="get" action="/manicures/{{ $manicure->id }}/polishes">
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
