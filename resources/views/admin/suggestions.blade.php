@extends('layouts.app')

@section('title', 'Suggested polishes')

@section('content')
    <h1>Suggested polishes</h1>
    <p>Private polishes whose owners suggested adding them to the public Catalog. Other private polishes aren't visible to you.</p>
    @if (session('status'))<p role="status">{{ session('status') }}</p>@endif
    @error('promote')<p>{{ $message }}</p>@enderror
    <ul>
        @forelse ($polishes as $polish)
            <li>
                <a href="{{ $polish->url() }}"><strong>{{ $polish->brand->name }}</strong> {{ $polish->name }}</a>
                suggested by {{ $polish->owner->display_name }}
                <form method="post" action="/admin/suggestions/{{ $polish->id }}/promote" style="display:inline">@csrf<button>Promote</button></form>
                <form method="post" action="/admin/suggestions/{{ $polish->id }}" style="display:inline">@csrf @method('delete')<button>Decline</button></form>
            </li>
        @empty
            <li>No suggestions waiting.</li>
        @endforelse
    </ul>
@endsection
