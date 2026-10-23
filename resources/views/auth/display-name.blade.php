@extends('layouts.app')

@section('title', 'Choose a display name')

@section('content')
    <div class="form-card">
        <h1>Choose a display name</h1>
        <p>This is the only name other people see. Your email address is never shown.</p>
        <form method="post" action="/display-name">
            @csrf
            <div class="field">
                <label for="display_name">Display name</label>
                <input id="display_name" name="display_name" value="{{ old('display_name') }}" maxlength="40" required autofocus autocomplete="nickname" @error('display_name') aria-invalid="true" aria-describedby="display_name-error" @enderror>
                <x-error name="display_name" />
            </div>
            <button class="btn-primary">Continue</button>
        </form>
    </div>
@endsection
