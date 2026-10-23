@extends('layouts.app')

@section('title', 'Sign in')

@section('content')
    <div class="form-card">
        <h1>Sign in</h1>
        <p>One more tap to finish signing in.</p>
        <form method="post">
            @csrf
            <button class="btn-primary">Sign in to Nail Polish Collector</button>
        </form>
    </div>
@endsection
