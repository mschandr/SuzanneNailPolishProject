@extends('layouts.app')

@section('title', $polish->exists ? 'Edit private polish' : 'Add a private polish')

@section('content')
    <h1>{{ $polish->exists ? 'Edit private polish' : 'Add a private polish' }}</h1>
    <p>Only you can see this polish. Check the <a href="/catalog">Catalog</a> first: if the shade is there, mark it owned instead.</p>

    <form method="post" enctype="multipart/form-data" action="{{ $polish->exists ? '/my-polishes/'.$polish->id : '/my-polishes' }}">
        @csrf
        @if ($polish->exists) @method('put') @endif

        <p><label>Brand <input name="brand" list="brands" value="{{ old('brand', $polish->brand?->name) }}" required></label></p>
        <datalist id="brands">@foreach ($brands as $brand)<option value="{{ $brand }}">@endforeach</datalist>

        <p><label>Shade name <input name="name" value="{{ old('name', $polish->name) }}" required></label></p>
        <p><label>Collection <input name="collection" value="{{ old('collection', $polish->collection) }}"></label></p>

        <p><label>Color family
            <select name="color_family_id">
                <option value="">Not sure</option>
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

        <p><label>Photo <input type="file" name="swatch" accept="image/jpeg,image/png,image/webp"></label></p>

        @if ($errors->any())<ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
        <button>Save</button>
    </form>

    @if ($polish->exists)
        <form method="post" action="/my-polishes/{{ $polish->id }}"
              onsubmit="return confirm('Delete this polish? Any bottles of it in your collection will be removed too.')">
            @csrf @method('delete')<button>Delete this polish</button>
        </form>
    @endif
@endsection
