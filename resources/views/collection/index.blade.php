@extends('layouts.app')

@section('title', 'My collection')

@section('content')
    <h1>My collection</h1>

    <form method="get" action="/collection" role="search" class="card">
        <div class="form-row">
            <div class="field grow-field">
                <label for="q">Search</label>
                <input id="q" type="search" name="q" value="{{ $q }}" placeholder="Shade or brand" autofocus class="full">
            </div>
            <div class="field">
                <label for="location">Location</label>
                <select id="location" name="location">
                    <option value="">Anywhere</option>
                    <option value="none" @selected($location === 'none')>No location</option>
                    @foreach ($locations as $loc)
                        <option value="{{ $loc->id }}" @selected($location === $loc->id)>{{ $loc->name }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn-primary">Search</button>
        </div>
        <label class="check"><input type="checkbox" name="history" value="1" @checked($history)> Include used up, given away, discarded</label>
    </form>

    @if (session('status'))<p role="status">{{ session('status') }}</p>@endif
    <p>{{ $bottles->total() }} {{ Str::plural('bottle', $bottles->total()) }} · <a href="/locations">Manage locations</a></p>

    @forelse ($bottles as $bottle)
        <div class="bottle">
            @if ($src = $bottle->polish->swatchSrc())<img class="swatch" src="{{ $src }}" alt="" loading="lazy" referrerpolicy="no-referrer">@else<span class="swatch" aria-hidden="true"></span>@endif
            <div class="body">
                <a href="{{ $bottle->polish->url() }}"><strong>{{ $bottle->polish->brand->name }}</strong> {{ $bottle->polish->name }}</a>
                <div class="meta">{{ $bottle->location?->name ?? 'No location' }}@if ($bottle->status !== \App\Models\Bottle::OWNED) · <span class="badge badge-muted">{{ Str::headline($bottle->status) }}</span>@endif</div>
                <details>
                    <summary>Change or remove</summary>
                    <div class="edit">
                        <form method="post" action="/bottles/{{ $bottle->id }}">
                            @csrf @method('put')
                            <select name="location_id" aria-label="Location">
                                <option value="">No location</option>
                                @foreach ($locations as $loc)
                                    <option value="{{ $loc->id }}" @selected($bottle->location_id === $loc->id)>{{ $loc->name }}</option>
                                @endforeach
                            </select>
                            <select name="status" aria-label="Status">
                                @foreach (\App\Models\Bottle::STATUSES as $status)
                                    <option value="{{ $status }}" @selected($bottle->status === $status)>{{ Str::headline($status) }}</option>
                                @endforeach
                            </select>
                            <button class="btn-primary btn-sm">Save</button>
                        </form>
                        <form method="post" action="/bottles/{{ $bottle->id }}" class="mt-md"
                              onsubmit="return confirm('Remove this bottle entirely? Use Status to keep it as history instead.')">
                            @csrf @method('delete')<button class="btn-danger btn-sm">Remove bottle</button>
                        </form>
                    </div>
                </details>
            </div>
        </div>
    @empty
        <div class="empty">
            <p>No bottles found.</p>
            <a class="btn btn-primary" href="/catalog">Add polishes from the Catalog</a>
        </div>
    @endforelse

    {{ $bottles->links() }}
@endsection
