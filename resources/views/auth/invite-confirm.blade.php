@extends('layouts.app')

@section('title', "You're invited")

@section('content')
    <div class="form-card">
        <h1>You're invited</h1>
        <p>Create your account to start keeping track of your polish.</p>
        <form method="post">
            @csrf
            <button class="btn-primary">Create my account</button>
        </form>
    </div>
@endsection
