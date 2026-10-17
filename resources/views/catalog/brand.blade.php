@extends('layouts.app')

@section('title', $brand->name)

@section('content')
    <p><a href="/catalog">All brands</a></p>
    <h1>{{ $brand->name }}</h1>
    <ul style="list-style:none;padding:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(8rem,1fr));gap:1rem">
        @foreach ($polishes as $polish)
            <li>
                <a href="{{ $polish->url() }}">
                    @if ($polish->swatch_path)
                        <img src="{{ Storage::disk('public')->url($polish->swatch_path) }}" alt="{{ $polish->name }}" loading="lazy" style="width:100%;aspect-ratio:1;object-fit:cover">
                    @endif
                    <div>{{ $polish->name }}</div>
                    @if ($ownedIds->has($polish->id))<div data-owned><strong>✓ Owned</strong></div>@endif
                </a>
            </li>
        @endforeach
    </ul>
    {{ $polishes->links() }}
@endsection
