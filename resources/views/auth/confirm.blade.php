@extends('layouts.app')

@section('content')
    <h1>Sign in</h1>
    <form method="post">
        @csrf
        <button>Sign in to Nail Polish Collector</button>
    </form>
@endsection
