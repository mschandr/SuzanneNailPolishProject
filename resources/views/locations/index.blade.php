@extends('layouts.app')

@section('title', 'Locations')

@section('content')
    <h1>Locations</h1>
    <p>Where you keep your polishes. Only you can see these.</p>
    <ul>
        @foreach ($locations as $location)
            <li>
                <form method="post" action="/locations/{{ $location->id }}" style="display:inline">
                    @csrf @method('put')
                    <input name="name" value="{{ $location->name }}" maxlength="100" required>
                    <button>Rename</button>
                </form>
                {{ $location->bottles_count }} {{ Str::plural('bottle', $location->bottles_count) }}
                <form method="post" action="/locations/{{ $location->id }}" style="display:inline"
                      onsubmit="return confirm('Delete this location? Its bottles stay in your collection with no location.')">
                    @csrf @method('delete')<button>Delete</button>
                </form>
            </li>
        @endforeach
    </ul>
    <form method="post" action="/locations">
        @csrf
        <label>New location <input name="name" maxlength="100" required></label>
        <button>Add</button>
    </form>
    @if ($errors->any())<p>{{ $errors->first() }}</p>@endif
@endsection
