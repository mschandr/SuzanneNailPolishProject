@extends('layouts.app')

@section('title', 'My private polishes')

@section('content')
    <h1>My private polishes</h1>
    <p>Shades that aren't in the public Catalog, such as limited editions and discontinued polishes. Only you can see these.</p>
    @if (session('status'))<p role="status">{{ session('status') }}</p>@endif
    <p><a class="btn btn-primary" href="/my-polishes/create">Add a private polish</a></p>
    @if ($polishes->isEmpty())
        <div class="empty"><p>You haven't added any yet.</p></div>
    @else
        <ul class="list">
            @foreach ($polishes as $polish)
                <li class="row">
                    @if ($src = $polish->swatchSrc())<img class="swatch" src="{{ $src }}" alt="" loading="lazy" referrerpolicy="no-referrer">@endif
                    <a class="grow" href="{{ $polish->url() }}"><strong>{{ $polish->brand->name }}</strong> {{ $polish->name }}</a>
                    <a class="btn btn-sm" href="/my-polishes/{{ $polish->id }}/edit">Edit</a>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
