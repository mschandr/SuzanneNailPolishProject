{{-- One listed polish. A private polish keeps its page private: strangers see its name but no link. --}}
@php($polish = $layer->polish)
@if (! $polish->isPrivate() || $isOwner)
    <a href="{{ $polish->url() }}"><strong>{{ $polish->brand->name }}</strong> {{ $polish->name }}</a>
@else
    <strong>{{ $polish->brand->name }}</strong> {{ $polish->name }}
@endif
@if ($isOwner && $layer->bottle_id)<small>(from my collection)</small>@endif
