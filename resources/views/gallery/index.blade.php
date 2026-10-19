@extends('layouts.app')

@section('title', 'Gallery')

@section('content')
    <h1>Gallery</h1>

    <form method="get" action="/gallery" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:end">
        <label>Brand <input name="brand" value="{{ $filters['brand'] }}"></label>
        <label>Polish <input name="polish" value="{{ $filters['polish'] }}"></label>
        <label>Finish
            <select name="finish_id">
                <option value="">Any</option>
                @foreach ($finishes as $finish)
                    <option value="{{ $finish->id }}" @selected($filters['finish_id'] === $finish->id)>{{ $finish->name }}</option>
                @endforeach
            </select></label>
        <label>Color family
            <select name="color_family_id">
                <option value="">Any</option>
                @foreach ($colorFamilies as $family)
                    <option value="{{ $family->id }}" @selected($filters['color_family_id'] === $family->id)>{{ $family->name }}</option>
                @endforeach
            </select></label>
        <label>Worn by <input name="person" value="{{ $filters['person'] }}"></label>
        @if ($filters['polish_id'] !== '')<input type="hidden" name="polish_id" value="{{ $filters['polish_id'] }}">@endif
        <label>Sort
            <select name="sort">
                <option value="new" @selected($sort === 'new')>Newest</option>
                <option value="loved" @selected($sort === 'loved')>Most hearted</option>
            </select></label>
        <button>Search</button>
        <a href="/gallery">Clear</a>
    </form>

    @include('gallery._tiles', ['manicures' => $manicures])
    {{ $manicures->links() }}
@endsection
