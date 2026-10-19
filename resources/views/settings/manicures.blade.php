@extends('layouts.app')

@section('title', 'Manicure privacy')

@section('content')
    <h1>Manicure privacy</h1>
    @if (session('status'))<p role="status">{{ session('status') }}</p>@endif
    <p>Your usual setting for new manicures. Each manicure can override it when you save it.</p>
    <form method="post" action="/settings/manicures">
        @csrf @method('put')
        <label><input type="radio" name="manicure_default_visibility" value="public" @checked($current === 'public')> Public — shown in the gallery and on your profile</label><br>
        <label><input type="radio" name="manicure_default_visibility" value="private" @checked($current === 'private')> Private — only you can see them</label>
        @error('manicure_default_visibility')<p>{{ $message }}</p>@enderror
        <p><button>Save</button></p>
    </form>
@endsection
