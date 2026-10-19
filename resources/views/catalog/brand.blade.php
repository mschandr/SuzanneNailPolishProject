@extends('layouts.app')

@section('title', $brand->name)

@section('content')
    <p><a href="/catalog">All brands</a></p>
    <h1>{{ $brand->name }}</h1>

    @if (session('status'))<p role="status">{{ session('status') }}</p>@endif
    @error('polish_ids')<p role="alert">{{ $message }}</p>@enderror
    @error('location_id')<p role="alert">{{ $message }}</p>@enderror
    @error('new_location')<p role="alert">{{ $message }}</p>@enderror

    {{-- Signed-in users can tick several polishes and add them to their collection in one go. --}}
    @if ($locations !== null)<form method="post" action="/bulk/own" id="bulk">@csrf</form>@endif

    <ul style="list-style:none;padding:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(8rem,1fr));gap:1rem">
        @foreach ($polishes as $polish)
            <li>
                @if ($locations !== null && ! $ownedIds->has($polish->id))
                    <label style="display:block"><input type="checkbox" name="polish_ids[]" value="{{ $polish->id }}" form="bulk" @checked(in_array($polish->id, old('polish_ids', [])))> Select</label>
                @endif
                <a href="{{ $polish->url() }}">
                    @if ($src = $polish->swatchSrc())
                        <img src="{{ $src }}" alt="{{ $polish->name }}" loading="lazy" referrerpolicy="no-referrer" style="width:100%;aspect-ratio:1;object-fit:cover">
                    @endif
                    <div>{{ $polish->name }}</div>
                    @if ($ownedIds->has($polish->id))<div data-owned><strong>✓ Owned</strong></div>@endif
                </a>
            </li>
        @endforeach
    </ul>

    @if ($locations !== null)
        <div style="position:sticky;bottom:0;background:#fff;border-top:1px solid #ddd;padding:.75rem 0;display:flex;gap:.5rem;flex-wrap:wrap;align-items:end">
            <button type="button" onclick="document.querySelectorAll('input[name=&quot;polish_ids[]&quot;]').forEach(c => c.checked = true)">Select all on this page</button>
            <label>Location
                <select name="location_id" form="bulk">
                    <option value="">No location</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                    @endforeach
                </select></label>
            <label>or new <input name="new_location" form="bulk" maxlength="100" value="{{ old('new_location') }}"></label>
            <button form="bulk">Add selected to my collection</button>
        </div>
    @endif

    {{ $polishes->links() }}
@endsection
