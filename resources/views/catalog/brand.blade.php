@extends('layouts.app')

@section('title', $brand->name)

@section('content')
    <p class="breadcrumb"><a href="/catalog">All brands</a></p>
    <h1>{{ $brand->name }}</h1>

    @if (session('status'))<p role="status">{{ session('status') }}</p>@endif
    @error('polish_ids')<p role="alert">{{ $message }}</p>@enderror
    @error('location_id')<p role="alert">{{ $message }}</p>@enderror
    @error('new_location')<p role="alert">{{ $message }}</p>@enderror

    {{-- Signed-in users can tick several polishes and add them to their collection in one go. --}}
    @if ($locations !== null)
        <form method="post" action="/bulk/own" id="bulk">@csrf</form>
        <p class="muted">Tick the polishes you own, then use the bar at the bottom to add them.</p>
    @endif

    <ul class="tiles">
        @foreach ($polishes as $polish)
            <li class="tile">
                @if ($locations !== null && ! $ownedIds->has($polish->id))
                    <label class="pick"><input type="checkbox" name="polish_ids[]" value="{{ $polish->id }}" form="bulk" @checked(in_array($polish->id, old('polish_ids', []))) aria-label="Select {{ $polish->name }}"></label>
                @endif
                <a href="{{ $polish->url() }}">
                    @if ($src = $polish->swatchSrc())
                        <img src="{{ $src }}" alt="" width="200" height="200" loading="lazy" referrerpolicy="no-referrer">
                    @else
                        <span class="noimg" aria-hidden="true">No photo</span>
                    @endif
                    <span class="caption">{{ $polish->name }}
                        @if ($ownedIds->has($polish->id))<small data-owned><strong>✓ Owned</strong></small>@endif
                    </span>
                </a>
            </li>
        @endforeach
    </ul>

    @if ($locations !== null)
        <div class="action-bar" id="bulk-bar">
            <div class="form-row">
                <button type="button" id="select-all">Select all on this page</button>
                <div class="field">
                    <label for="bulk-location">Location</label>
                    <select id="bulk-location" name="location_id" form="bulk">
                        <option value="">No location</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}">{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="bulk-new-location">or a new one</label>
                    <input id="bulk-new-location" name="new_location" form="bulk" maxlength="100" value="{{ old('new_location') }}">
                </div>
                <button form="bulk" class="btn-primary">Add selected to my collection <span data-count></span></button>
            </div>
        </div>
    @endif

    {{ $polishes->links() }}
@endsection
