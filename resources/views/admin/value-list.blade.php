@extends('layouts.app')

@section('title', $title)

@section('content')
    <h1>{{ $title }}</h1>
    @include('admin._nav')
    @if ($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
    <ul class="list">
        @foreach ($values as $value)
            <li>
                <form method="post" action="{{ $path }}/{{ $value->id }}" class="row">
                    @csrf @method('put')
                    <label class="sr-only" for="v-{{ $value->id }}">Name of {{ $value->name }}</label>
                    <input id="v-{{ $value->id }}" name="name" value="{{ $value->name }}" maxlength="60" required class="grow-input">
                    <button class="btn-sm">Rename</button>
                </form>
            </li>
        @endforeach
    </ul>
    <form method="post" action="{{ $path }}" class="card form-row">
        @csrf
        <div class="field"><label for="new-value">Add a value</label><input id="new-value" name="name" maxlength="60" required></div>
        <button class="btn-primary">Add</button>
    </form>
@endsection
