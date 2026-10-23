@extends('layouts.app')

@section('title', 'Suggested polishes')

@section('content')
    <h1>Suggested polishes</h1>
    @include('admin._nav')
    <p>Private polishes whose owners suggested adding them to the public Catalog. Other private polishes aren't visible to you.</p>
    @if (session('status'))<p role="status">{{ session('status') }}</p>@endif
    @error('promote')<p role="alert">{{ $message }}</p>@enderror
    <ul class="list">
        @forelse ($polishes as $polish)
            <li class="row">
                <span class="grow"><a href="{{ $polish->url() }}"><strong>{{ $polish->brand->name }}</strong> {{ $polish->name }}</a>
                suggested by {{ $polish->owner->display_name }}</span>
                <form method="post" action="/admin/suggestions/{{ $polish->id }}/promote">@csrf<button class="btn-primary btn-sm">Promote</button></form>
                <form method="post" action="/admin/suggestions/{{ $polish->id }}">@csrf @method('delete')<button class="btn-sm">Decline</button></form>
            </li>
        @empty
            <li>No suggestions waiting.</li>
        @endforelse
    </ul>
@endsection
