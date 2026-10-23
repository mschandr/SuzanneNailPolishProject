@extends('layouts.app')

@section('title', 'Gallery')

@section('content')
    <h1>Gallery</h1>

    @php
        $active = collect([
            'brand' => $filters['brand'] !== '' ? 'Brand: '.$filters['brand'] : null,
            'polish' => $filters['polish'] !== '' ? 'Polish: '.$filters['polish'] : null,
            'finish' => $filters['finish_id'] !== '' ? 'Finish: '.optional($finishes->firstWhere('id', $filters['finish_id']))->name : null,
            'color' => $filters['color_family_id'] !== '' ? 'Color family: '.optional($colorFamilies->firstWhere('id', $filters['color_family_id']))->name : null,
            'person' => $filters['person'] !== '' ? 'Worn by: '.$filters['person'] : null,
            'polish_id' => $filters['polish_id'] !== '' ? 'One specific polish' : null,
        ])->filter();
    @endphp

    <details class="filters" @if ($active->isNotEmpty()) open @endif>
        <summary>Filters and sort @if ($active->isNotEmpty())<span class="badge">{{ $active->count() }} on</span>@endif</summary>
        <form method="get" action="/gallery" role="search">
            <div class="form-row">
                <div class="field"><label for="brand">Brand</label><input id="brand" name="brand" value="{{ $filters['brand'] }}"></div>
                <div class="field"><label for="polish">Polish</label><input id="polish" name="polish" value="{{ $filters['polish'] }}"></div>
                <div class="field">
                    <label for="finish_id">Finish</label>
                    <select id="finish_id" name="finish_id">
                        <option value="">Any</option>
                        @foreach ($finishes as $finish)
                            <option value="{{ $finish->id }}" @selected($filters['finish_id'] === $finish->id)>{{ $finish->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="color_family_id">Color family</label>
                    <select id="color_family_id" name="color_family_id">
                        <option value="">Any</option>
                        @foreach ($colorFamilies as $family)
                            <option value="{{ $family->id }}" @selected($filters['color_family_id'] === $family->id)>{{ $family->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field"><label for="person">Worn by</label><input id="person" name="person" value="{{ $filters['person'] }}"></div>
                @if ($filters['polish_id'] !== '')<input type="hidden" name="polish_id" value="{{ $filters['polish_id'] }}">@endif
                <div class="field">
                    <label for="sort">Sort</label>
                    <select id="sort" name="sort">
                        <option value="new" @selected($sort === 'new')>Newest</option>
                        <option value="loved" @selected($sort === 'loved')>Most hearted</option>
                    </select>
                </div>
            </div>
            <div class="actions">
                <button class="btn-primary">Search</button>
                <a class="btn" href="/gallery">Clear</a>
            </div>
        </form>
    </details>

    @if ($active->isNotEmpty())
        <p class="chips" aria-label="Active filters">@foreach ($active as $label)<span class="chip">{{ $label }}</span>@endforeach</p>
    @endif

    @include('gallery._tiles', ['manicures' => $manicures])
    {{ $manicures->links() }}
@endsection
