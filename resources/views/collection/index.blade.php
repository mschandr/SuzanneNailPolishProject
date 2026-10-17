@extends('layouts.app')

@section('title', 'My collection')

@section('content')
    <h1>My collection</h1>

    <form method="get" action="/collection" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:end">
        <label>Search <input type="search" name="q" value="{{ $q }}" placeholder="Shade or brand" autofocus></label>
        <label>Location
            <select name="location">
                <option value="">Anywhere</option>
                <option value="none" @selected($location === 'none')>No location</option>
                @foreach ($locations as $loc)
                    <option value="{{ $loc->id }}" @selected($location === $loc->id)>{{ $loc->name }}</option>
                @endforeach
            </select></label>
        <label><input type="checkbox" name="history" value="1" @checked($history)> Include used up, given away, discarded</label>
        <button>Search</button>
    </form>

    @if (session('status'))<p role="status">{{ session('status') }}</p>@endif
    <p>{{ $bottles->total() }} {{ Str::plural('bottle', $bottles->total()) }} <a href="/locations">Manage locations</a></p>

    @forelse ($bottles as $bottle)
        <div style="border-top:1px solid #ddd;padding:.5rem 0">
            <a href="{{ $bottle->polish->url() }}"><strong>{{ $bottle->polish->brand->name }}</strong> {{ $bottle->polish->name }}</a>
            <div>{{ $bottle->location?->name ?? 'No location' }}@if ($bottle->status !== \App\Models\Bottle::OWNED) · {{ Str::headline($bottle->status) }}@endif</div>
            <form method="post" action="/bottles/{{ $bottle->id }}" style="display:inline">
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
                <button>Save</button>
            </form>
            <form method="post" action="/bottles/{{ $bottle->id }}" style="display:inline"
                  onsubmit="return confirm('Remove this bottle entirely? Use Status to keep it as history instead.')">
                @csrf @method('delete')<button>Remove</button>
            </form>
        </div>
    @empty
        <p>No bottles found.</p>
    @endforelse

    {{ $bottles->links() }}
@endsection
