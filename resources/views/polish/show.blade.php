@extends('layouts.app')

@section('title', $polish->brand->name.' '.$polish->name)

@section('content')
    <p><a href="/catalog/{{ $polish->brand->slug }}">{{ $polish->brand->name }}</a></p>
    <h1>{{ $polish->name }}</h1>
    @if ($polish->swatch_path)
        <img src="{{ Storage::disk('public')->url($polish->swatch_path) }}" alt="{{ $polish->name }}" style="max-width:100%;width:24rem">
    @endif
    <dl>
        <dt>Brand</dt><dd>{{ $polish->brand->name }}</dd>
        @if ($polish->collection)<dt>Collection</dt><dd>{{ $polish->collection }}</dd>@endif
        @if ($polish->colorFamily)<dt>Color family</dt><dd>{{ $polish->colorFamily->name }}</dd>@endif
        @if ($polish->finishes->isNotEmpty())
            <dt>Finish</dt><dd>{{ $polish->finishes->sortBy('name_key')->pluck('name')->join(', ') }}</dd>
        @endif
    </dl>
    @if ($polish->vendor_url)
        <p><a href="{{ $polish->vendor_url }}" rel="noopener nofollow">Buy from the vendor</a></p>
    @endif
    @if (auth()->user()?->is_admin)
        <p><a href="/admin/polishes/{{ $polish->id }}/edit">Edit (Admin)</a></p>
    @endif
@endsection
