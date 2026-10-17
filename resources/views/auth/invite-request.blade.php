@extends('layouts.app')

@section('content')
    <h1>Request an invite</h1>
    <p>New sign-ups are closed. Leave your email address and you may be sent an invite.</p>
    <form method="post" action="/invite-request">
        @csrf
        <label>Email <input type="email" name="email" value="{{ old('email') }}" required autofocus></label>
        @error('email')<p>{{ $message }}</p>@enderror
        <button>Request an invite</button>
    </form>
@endsection
