@extends('layouts.app')

@section('title', 'My private polishes')

@section('content')
    <h1>My private polishes</h1>
    <p>Shades that aren't in the public Catalog, such as limited editions and discontinued polishes. Only you can see these.</p>
    @if (session('status'))<p role="status">{{ session('status') }}</p>@endif
    <p><a href="/my-polishes/create">Add a private polish</a></p>
    <ul>
        @forelse ($polishes as $polish)
            <li>
                <a href="{{ $polish->url() }}"><strong>{{ $polish->brand->name }}</strong> {{ $polish->name }}</a>
                · <a href="/my-polishes/{{ $polish->id }}/edit">Edit</a>
            </li>
        @empty
            <li>You haven't added any yet.</li>
        @endforelse
    </ul>
@endsection
