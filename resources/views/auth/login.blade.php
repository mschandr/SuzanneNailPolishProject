@extends('layouts.app')

@section('title', 'Sign in')

@section('content')
    <div class="form-card">
        <h1>Sign in</h1>
        <p>No password needed. Enter your email and we'll send you a link that signs you in. New here? The same link creates your account.</p>
        <form method="post" action="/login">
            @csrf
            <div class="field">
                <label for="email">Email address</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                <x-error name="email" />
            </div>
            <button class="btn-primary">Email me a sign-in link</button>
        </form>
        @unless ($open)
            <p class="mt-lg">New sign-ups are closed. Don't have an account? <a href="/invite-request">Ask to request an invite</a>.</p>
        @endunless
    </div>
@endsection
