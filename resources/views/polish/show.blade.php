@extends('layouts.app')

@section('title', $polish->brand->name.' '.$polish->name)

@section('content')
    <p class="breadcrumb"><a href="/catalog/{{ $polish->brand->slug }}">{{ $polish->brand->name }}</a></p>
    <h1>{{ $polish->name }}</h1>
    @if ($src = $polish->swatchSrc())
        <img src="{{ $src }}" alt="{{ $polish->name }}" referrerpolicy="no-referrer" class="swatch-lg" width="400" height="400">
    @endif
    <dl class="card">
        <dt>Brand</dt><dd>{{ $polish->brand->name }}</dd>
        @if ($polish->collection)<dt>Collection</dt><dd>{{ $polish->collection }}</dd>@endif
        @if ($polish->colorFamily)<dt>Color family</dt><dd>{{ $polish->colorFamily->name }}</dd>@endif
        @if ($polish->finishes->isNotEmpty())
            <dt>Finish</dt><dd>{{ $polish->finishes->sortBy('name_key')->pluck('name')->join(', ') }}</dd>
        @endif
    </dl>
    @if ($polish->vendor_url)
        <p><a class="btn" href="{{ $polish->vendor_url }}" rel="noopener nofollow">Buy from the vendor</a></p>
    @endif
    @if ($wornBy->isNotEmpty())
        <section>
            <h2>Worn by others</h2>
            @include('gallery._tiles', ['manicures' => $wornBy])
            <p><a href="/gallery?polish_id={{ $polish->id }}">See all in the Gallery</a></p>
        </section>
    @endif

    <section class="card">
        @if ($bottles === null)
            <p><a href="/login">Sign in</a> to track whether you own this.</p>
        @else
            @if (session('status'))<p role="status">{{ session('status') }}</p>@endif
            @php($owned = $bottles->where('status', \App\Models\Bottle::OWNED))
            <p>{{ $owned->isEmpty() ? 'You don\'t own this yet.' : 'You own '.$owned->count() }}</p>
            <form method="post" action="/polishes/{{ $polish->id }}/bottles">
                @csrf
                <div class="form-row">
                    <div class="field">
                        <label for="location_id">Location</label>
                        <select id="location_id" name="location_id">
                            <option value="">No location</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}">{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field"><label for="new_location">or a new one</label><input id="new_location" name="new_location" maxlength="100"></div>
                    <button class="btn-primary">{{ $owned->isEmpty() ? 'I own this' : 'Add another' }}</button>
                </div>
                <x-error name="location_id" />
                <x-error name="new_location" />
            </form>
        @endif
    </section>
    @if ($polish->isPrivate() && $polish->user_id === auth()->id())
        <section class="card">
            <p><strong>Private</strong> — only you can see this polish. <a href="/my-polishes/{{ $polish->id }}/edit">Edit</a></p>
            @if ($polish->promotion_requested_at)
                <form method="post" action="/my-polishes/{{ $polish->id }}/suggest" class="row">@csrf @method('delete')
                    <button class="btn-sm">Withdraw my suggestion</button> <span class="meta">(waiting for the Admin)</span></form>
            @else
                <form method="post" action="/my-polishes/{{ $polish->id }}/suggest">@csrf
                    <button class="btn-sm">Suggest for the public Catalog</button></form>
            @endif
        </section>
    @elseif (! $polish->isPrivate() && auth()->user()?->is_admin)
        <p><a href="/admin/polishes/{{ $polish->id }}/edit">Edit (Admin)</a></p>
    @endif
@endsection
