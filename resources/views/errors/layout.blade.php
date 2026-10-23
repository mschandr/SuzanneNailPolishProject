@extends('layouts.app')

@section('title', $heading)

@section('content')
    <div class="error-page">
        <p class="code" aria-hidden="true">{{ $code }}</p>
        <h1>{{ $heading }}</h1>
        <p>{{ $message }}</p>
        <div class="actions center">
            @yield('extra')
            <a class="btn btn-primary" href="{{ auth()->check() ? '/home' : '/' }}">Go to the home page</a>
            <a class="btn" href="/catalog">Browse the Catalog</a>
        </div>
    </div>
@endsection
