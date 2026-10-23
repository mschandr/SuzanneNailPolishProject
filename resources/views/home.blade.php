@extends('layouts.app')

@section('title', 'Home')

@section('content')
    <h1>Hello, {{ auth()->user()->display_name }}.</h1>

    <section class="card" aria-labelledby="own-heading">
        <h2 id="own-heading" class="mt-0">Do I already own this?</h2>
        <form method="get" action="/collection" class="form-row">
            <div class="field grow-field">
                <label for="home-q" class="sr-only">Shade or brand</label>
                <input id="home-q" type="search" name="q" placeholder="Search my collection by shade or brand" class="full">
            </div>
            <button class="btn-primary">Search</button>
        </form>
        <p class="mt-md">You have {{ $ownedCount }} {{ Str::plural('bottle', $ownedCount) }} in your collection.</p>
    </section>

    <section aria-labelledby="quick-heading">
        <h2 id="quick-heading">Quick actions</h2>
        <ul class="link-list">
            <li><a href="/catalog">Browse the Catalog</a></li>
            <li><a href="/manicures/create">New manicure</a></li>
            <li><a href="/my-polishes/create">Add a private polish</a></li>
            <li><a href="/gallery">Open the Gallery</a></li>
        </ul>
    </section>

    @if ($recent->isNotEmpty())
        <section aria-labelledby="recent-heading">
            <h2 id="recent-heading">Recently added</h2>
            <ul class="list">
                @foreach ($recent as $bottle)
                    <li class="row">
                        @if ($src = $bottle->polish->swatchSrc())<img class="swatch" src="{{ $src }}" alt="" loading="lazy" referrerpolicy="no-referrer">@endif
                        <a class="grow" href="{{ $bottle->polish->url() }}"><strong>{{ $bottle->polish->brand->name }}</strong> {{ $bottle->polish->name }}</a>
                    </li>
                @endforeach
            </ul>
            <p><a href="/collection">See my whole collection</a></p>
        </section>
    @else
        <div class="empty">
            <p>Your collection is empty.</p>
            <a class="btn btn-primary" href="/catalog">Add your first polishes</a>
        </div>
    @endif
@endsection
