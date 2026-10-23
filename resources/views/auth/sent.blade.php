@extends('layouts.app')

@section('title', 'Check your email')

@section('content')
    <div class="form-card">
        <h1>Check your email</h1>
        <p>If that address can sign in, a link is on its way. It expires in 15 minutes.</p>
        <h2>Didn't get it?</h2>
        <ul>
            <li>Look in your spam or junk folder.</li>
            <li>Check you typed the address correctly.</li>
            <li>Wait a minute, then <a href="/login">ask for a new link</a>.</li>
        </ul>
    </div>
@endsection
