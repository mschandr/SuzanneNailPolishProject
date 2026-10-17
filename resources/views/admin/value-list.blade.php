@extends('layouts.app')

@section('content')
    <h1>{{ $title }}</h1>
    <ul>
        @foreach ($values as $value)
            <li>
                <form method="post" action="{{ $path }}/{{ $value->id }}">
                    @csrf @method('put')
                    <input name="name" value="{{ $value->name }}" maxlength="60" required>
                    <button>Rename</button>
                </form>
            </li>
        @endforeach
    </ul>
    <form method="post" action="{{ $path }}">
        @csrf
        <label>Add a value <input name="name" maxlength="60" required></label>
        <button>Add</button>
    </form>
    @if ($errors->any())<p>{{ $errors->first() }}</p>@endif
@endsection
