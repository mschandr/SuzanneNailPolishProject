@extends('layouts.app')

@section('content')
    <p>Hello, {{ auth()->user()->display_name }}.</p>
    <form method="post" action="/logout">@csrf<button>Sign out</button></form>
@endsection
