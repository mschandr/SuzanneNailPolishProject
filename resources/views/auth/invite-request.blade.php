@extends('layouts.app')

@section('title', 'Request an invite')

@section('content')
    <div class="form-card">
        <h1>Request an invite</h1>
        <p>New sign-ups are closed. Leave your email address and you may be sent an invite.</p>
        <form method="post" action="/invite-request">
            @csrf
            <div class="field">
                <label for="email">Email address</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                <x-error name="email" />
            </div>
            <button class="btn-primary">Request an invite</button>
        </form>
    </div>
@endsection
