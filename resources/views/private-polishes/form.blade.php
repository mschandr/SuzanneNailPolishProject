@extends('layouts.app')

@section('title', $polish->exists ? 'Edit private polish' : 'Add a private polish')

@section('content')
    <div class="form-card">
        <h1>{{ $polish->exists ? 'Edit private polish' : 'Add a private polish' }}</h1>
        <p>Only you can see this polish. Check the <a href="/catalog">Catalog</a> first: if the shade is there, mark it owned instead.</p>

        <form method="post" enctype="multipart/form-data" action="{{ $polish->exists ? '/my-polishes/'.$polish->id : '/my-polishes' }}" data-busy>
            @csrf
            @if ($polish->exists) @method('put') @endif
            <x-errors />

            <div class="field"><label for="brand">Brand</label><input id="brand" name="brand" list="brands" value="{{ old('brand', $polish->brand?->name) }}" required></div>
            <datalist id="brands">@foreach ($brands as $brand)<option value="{{ $brand }}">@endforeach</datalist>

            <div class="field"><label for="name">Shade name</label><input id="name" name="name" value="{{ old('name', $polish->name) }}" required></div>
            <div class="field"><label for="collection">Collection <span class="hint">Optional</span></label><input id="collection" name="collection" value="{{ old('collection', $polish->collection) }}"></div>

            <div class="field">
                <label for="color_family_id">Color family</label>
                <select id="color_family_id" name="color_family_id">
                    <option value="">Not sure</option>
                    @foreach ($colorFamilies as $family)
                        <option value="{{ $family->id }}" @selected(old('color_family_id', $polish->color_family_id) === $family->id)>{{ $family->name }}</option>
                    @endforeach
                </select>
            </div>

            <fieldset class="field">
                <legend><strong>Finishes</strong></legend>
                @php($chosen = old('finish_ids', $polish->exists ? $polish->finishes->pluck('id')->all() : []))
                @foreach ($finishes as $finish)
                    <label class="check"><input type="checkbox" name="finish_ids[]" value="{{ $finish->id }}" @checked(in_array($finish->id, $chosen))> {{ $finish->name }}</label>
                @endforeach
            </fieldset>

            <div class="field"><label for="swatch">Photo <span class="hint">Optional. JPEG, PNG or WebP.</span></label><input id="swatch" type="file" name="swatch" accept="image/jpeg,image/png,image/webp"></div>

            <div class="actions">
                <button class="btn-primary">Save</button>
                <a class="btn" href="/my-polishes">Cancel</a>
            </div>
            <p class="busy-note" hidden>Saving… please wait.</p>
        </form>

        @if ($polish->exists)
            <div class="danger-zone">
                <h2>Delete this polish</h2>
                <form method="post" action="/my-polishes/{{ $polish->id }}"
                      onsubmit="return confirm('Delete this polish? Any bottles of it in your collection will be removed too.')">
                    @csrf @method('delete')<button class="btn-danger">Delete this polish</button>
                </form>
            </div>
        @endif
    </div>
@endsection
