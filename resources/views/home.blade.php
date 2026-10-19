@extends('layouts.app')

@section('content')
    <p>Hello, {{ auth()->user()->display_name }}.</p>
    <p>You have {{ $ownedCount }} {{ Str::plural('bottle', $ownedCount) }} in your collection.</p>
    <ul>
        <li><a href="/catalog">Browse the Catalog</a></li>
        <li><a href="/collection">My collection</a></li>
        <li><a href="/help">Help</a></li>
    </ul>
@endsection
