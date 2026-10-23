@extends('layouts.app')

@section('title', 'Catalog')

@section('content')
    <h1>Catalog</h1>
    @include('catalog._search-form')

    @php($letters = $brands->groupBy(fn ($b) => mb_strtoupper(mb_substr($b->name, 0, 1))))
    @if ($letters->count() > 5)
        <nav class="az" aria-label="Jump to a letter">
            @foreach ($letters as $letter => $group)<a href="#letter-{{ $loop->index }}">{{ $letter }}</a>@endforeach
        </nav>
    @endif

    @foreach ($letters as $letter => $group)
        <section aria-labelledby="letter-{{ $loop->index }}">
            <h2 id="letter-{{ $loop->index }}" class="mt-md">{{ $letter }}</h2>
            <ul class="link-list">
                @foreach ($group as $brand)
                    <li><a href="/catalog/{{ $brand->slug }}">{{ $brand->name }}</a></li>
                @endforeach
            </ul>
        </section>
    @endforeach
@endsection
