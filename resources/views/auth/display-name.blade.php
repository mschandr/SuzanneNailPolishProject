@extends('layouts.app')

@section('content')
    <h1>Choose a display name</h1>
    <p>This is the only name other people see. Your email address is never shown.</p>
    <form method="post" action="/display-name">
        @csrf
        <label>Display name <input name="display_name" value="{{ old('display_name') }}" maxlength="40" required autofocus></label>
        @error('display_name')<p>{{ $message }}</p>@enderror
        <button>Continue</button>
    </form>
@endsection
