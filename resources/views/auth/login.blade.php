@extends('layouts.app')

@section('content')
    <h1>Sign in</h1>
    <form method="post" action="/login">
        @csrf
        <label>Email <input type="email" name="email" value="{{ old('email') }}" required autofocus></label>
        @error('email')<p>{{ $message }}</p>@enderror
        <button>Email me a sign-in link</button>
    </form>
@endsection
