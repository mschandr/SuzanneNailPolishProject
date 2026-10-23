@extends('layouts.app')

@section('title', 'Locations')

@section('content')
    <h1>Locations</h1>
    <p>Where you keep your polishes. Only you can see these.</p>
    @if (session('status'))<p role="status">{{ session('status') }}</p>@endif
    @if ($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif

    <ul class="list">
        @foreach ($locations as $location)
            <li>
                <div class="row">
                    <form method="post" action="/locations/{{ $location->id }}" class="row grow">
                        @csrf @method('put')
                        <label class="sr-only" for="loc-{{ $location->id }}">Name of {{ $location->name }}</label>
                        <input id="loc-{{ $location->id }}" name="name" value="{{ $location->name }}" maxlength="100" required class="grow-input">
                        <button class="btn-sm">Rename</button>
                    </form>
                    <span class="meta">{{ $location->bottles_count }} {{ Str::plural('bottle', $location->bottles_count) }}</span>
                    <form method="post" action="/locations/{{ $location->id }}"
                          onsubmit="return confirm('Delete this location? Its bottles stay in your collection with no location.')">
                        @csrf @method('delete')<button class="btn-danger btn-sm">Delete</button>
                    </form>
                </div>
            </li>
        @endforeach
    </ul>

    <form method="post" action="/locations" class="card form-row">
        @csrf
        <div class="field"><label for="new-location">New location</label><input id="new-location" name="name" maxlength="100" required></div>
        <button class="btn-primary">Add</button>
    </form>
@endsection
