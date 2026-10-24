@extends('layouts.app')

@section('title', 'Find a polish')

@section('content')
    <h1>Polishes</h1>
    @include('admin._nav')

    <form method="get" action="/admin/polishes" role="search" class="form-row mb-md">
        <div class="field grow-field">
            <label for="admin-q" class="sr-only">Search by shade or brand</label>
            <input id="admin-q" type="search" name="q" value="{{ $q }}" maxlength="100" placeholder="Search by shade or brand" class="full" autofocus>
        </div>
        <button class="btn-primary">Search</button>
        <a class="btn" href="/admin/polishes/create">Add a polish</a>
    </form>

    <p>{{ $polishes->total() }} {{ $polishes->total() === 1 ? 'polish' : 'polishes' }}{{ $q !== '' ? ' matching “'.$q.'”' : ' in the Catalog' }}</p>

    @if ($polishes->isEmpty())
        <div class="empty"><p>Nothing matches.</p></div>
    @else
        <ul class="list">
            @foreach ($polishes as $polish)
                <li class="row">
                    @if ($src = $polish->swatchSrc())<img class="swatch" src="{{ $src }}" alt="" loading="lazy" referrerpolicy="no-referrer">@endif
                    <span class="grow"><a href="{{ $polish->url() }}"><strong>{{ $polish->brand->name }}</strong> {{ $polish->name }}</a>
                        @if ($polish->needs_review)<span class="badge badge-warn">needs review</span>@endif</span>
                    <a class="btn btn-sm" href="/admin/polishes/{{ $polish->id }}/edit">Edit</a>
                </li>
            @endforeach
        </ul>
        {{ $polishes->links() }}
    @endif
@endsection
