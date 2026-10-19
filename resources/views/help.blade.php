@extends('layouts.app')

@section('title', 'Help')

@section('content')
    <h1>Help</h1>
    <p><a href="/welcome">Take the tour again</a></p>
    <ul>
        <li><strong>Add polishes you own:</strong> open a brand in the <a href="/catalog">Catalog</a>, tick the shades and press "Add selected".</li>
        <li><strong>Check if you own one:</strong> <a href="/collection">My collection</a> searches by shade or brand.</li>
        <li><strong>Storage places:</strong> create them under <a href="/locations">Locations</a>.</li>
        <li><strong>Not in the Catalog?</strong> Add it as a <a href="/my-polishes">private polish</a>.</li>
    </ul>
@endsection
