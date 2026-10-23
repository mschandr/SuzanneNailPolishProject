@extends('layouts.app')

@section('title', $polish->exists ? 'Edit polish' : 'Add a polish')

@section('content')
    <div class="form-card">
        <h1>{{ $polish->exists ? 'Edit polish' : 'Add a polish' }}</h1>
        @include('admin._nav')
        <form method="post" enctype="multipart/form-data" action="{{ $polish->exists ? '/admin/polishes/'.$polish->id : '/admin/polishes' }}" data-busy>
            @csrf
            @if ($polish->exists) @method('put') @endif
            <x-errors />

            <div class="field"><label for="brand">Brand</label><input id="brand" name="brand" list="brands" value="{{ old('brand', $polish->brand?->name) }}" required></div>
            <datalist id="brands">@foreach ($brands as $brand)<option value="{{ $brand }}">@endforeach</datalist>

            <div class="field"><label for="name">Shade name</label><input id="name" name="name" value="{{ old('name', $polish->name) }}" required></div>
            <div class="field"><label for="collection">Collection <span class="hint">Optional</span></label><input id="collection" name="collection" value="{{ old('collection', $polish->collection) }}"></div>

            <div class="field">
                <label for="color_family_id">Color family</label>
                <select id="color_family_id" name="color_family_id" required>
                    <option value="">Choose…</option>
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

            <div class="field"><label for="vendor_url">Vendor link <span class="hint">Optional</span></label><input id="vendor_url" type="url" name="vendor_url" value="{{ old('vendor_url', $polish->vendor_url) }}"></div>
            <div class="field"><label for="swatch">Swatch photo <span class="hint">Optional. JPEG, PNG or WebP.</span></label><input id="swatch" type="file" name="swatch" accept="image/jpeg,image/png,image/webp"></div>

            <button class="btn-primary">Save</button>
            <p class="busy-note" hidden>Saving… please wait.</p>
        </form>
    </div>
@endsection
