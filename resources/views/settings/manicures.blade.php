@extends('layouts.app')

@section('title', 'Manicure privacy')

@section('content')
    <div class="form-card">
        <h1>Manicure privacy</h1>
        @if (session('status'))<p role="status">{{ session('status') }}</p>@endif
        <p>Your usual setting for new manicures. Each manicure can override it when you save it.</p>
        <form method="post" action="/settings/manicures">
            @csrf @method('put')
            <fieldset class="card">
                <legend>Who can see my manicures</legend>
                <label class="check"><input type="radio" name="manicure_default_visibility" value="public" @checked($current === 'public')> Public — shown in the gallery and on your profile</label>
                <label class="check"><input type="radio" name="manicure_default_visibility" value="private" @checked($current === 'private')> Private — only you can see them</label>
                <x-error name="manicure_default_visibility" />
            </fieldset>
            <button class="btn-primary">Save</button>
        </form>
    </div>
@endsection
