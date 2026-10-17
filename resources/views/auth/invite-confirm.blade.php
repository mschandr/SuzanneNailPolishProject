@extends('layouts.app')

@section('content')
    <h1>You're invited</h1>
    <form method="post">
        @csrf
        <button>Create my account</button>
    </form>
@endsection
