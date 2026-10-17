@extends('layouts.app')

@section('content')
    <h1>{{ $polish->exists ? 'Edit polish' : 'Add a polish' }}</h1>
    <form method="post" enctype="multipart/form-data" action="{{ $polish->exists ? '/admin/polishes/'.$polish->id : '/admin/polishes' }}">
        @csrf
        @if ($polish->exists) @method('put') @endif

        <p><label>Brand <input name="brand" list="brands" value="{{ old('brand', $polish->brand?->name) }}" required></label></p>
        <datalist id="brands">@foreach ($brands as $brand)<option value="{{ $brand }}">@endforeach</datalist>

        <p><label>Shade name <input name="name" value="{{ old('name', $polish->name) }}" required></label></p>
        <p><label>Collection <input name="collection" value="{{ old('collection', $polish->collection) }}"></label></p>

        <p><label>Color family
            <select name="color_family_id" required>
                <option value="">Choose…</option>
                @foreach ($colorFamilies as $family)
                    <option value="{{ $family->id }}" @selected(old('color_family_id', $polish->color_family_id) === $family->id)>{{ $family->name }}</option>
                @endforeach
            </select></label></p>

        <fieldset>
            <legend>Finishes</legend>
            @php($chosen = old('finish_ids', $polish->exists ? $polish->finishes->pluck('id')->all() : []))
            @foreach ($finishes as $finish)
                <label><input type="checkbox" name="finish_ids[]" value="{{ $finish->id }}" @checked(in_array($finish->id, $chosen))> {{ $finish->name }}</label>
            @endforeach
        </fieldset>

        <p><label>Vendor link <input type="url" name="vendor_url" value="{{ old('vendor_url', $polish->vendor_url) }}"></label></p>
        <p><label>Swatch photo <input type="file" name="swatch" accept="image/jpeg,image/png,image/webp"></label></p>

        @if ($errors->any())<ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
        <button>Save</button>
    </form>
@endsection
