@extends('layouts.app')

@section('title', 'Search the Catalog')

@section('content')
    <p class="breadcrumb"><a href="/catalog">All brands</a></p>
    <h1>Search the Catalog</h1>
    @include('catalog._search-form')

    <p>{{ $results->count() }}{{ $truncated ? '+' : '' }} {{ $results->count() === 1 && ! $truncated ? 'polish' : 'polishes' }} for “{{ $q }}”</p>

    @if ($results->isEmpty())
        <div class="empty">
            <p>Nothing matches “{{ $q }}”.</p>
            <a class="btn" href="/my-polishes/create">Add it as a private polish</a>
        </div>
    @else
        <ul class="list">
            @foreach ($results as $polish)
                <li class="row">
                    @if ($src = $polish->swatchSrc())<img class="swatch" src="{{ $src }}" alt="" loading="lazy" referrerpolicy="no-referrer">@endif
                    <a class="grow" href="{{ $polish->url() }}"><strong>{{ $polish->brand->name }}</strong> {{ $polish->name }}</a>
                    @if ($ownedIds->has($polish->id))<span class="badge" data-owned>✓ Owned</span>@endif
                </li>
            @endforeach
        </ul>
        @if ($truncated)<p>Showing the first {{ $limit }} matches. Search for something more specific to narrow it down.</p>@endif
    @endif
@endsection
