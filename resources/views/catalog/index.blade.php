@extends('layouts.app')

@section('title', 'Catalog')

@section('content')
    <h1>Catalog</h1>
    <ul>
        @foreach ($brands as $brand)
            <li><a href="/catalog/{{ $brand->slug }}">{{ $brand->name }}</a></li>
        @endforeach
    </ul>
@endsection
