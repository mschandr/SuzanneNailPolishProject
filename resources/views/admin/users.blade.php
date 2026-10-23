@extends('layouts.app')

@section('title', 'Admins')

@section('content')
    <h1>Admins</h1>
    @include('admin._nav')
    <ul class="list">
        @foreach ($admins as $admin)
            <li class="row">
                <span class="grow">{{ $admin->display_name ?? $admin->email }} ({{ $admin->email }})</span>
                @unless ($admin->is(auth()->user()))
                    <form method="post" action="/admin/users/{{ $admin->id }}"
                          onsubmit="return confirm('Remove Admin rights from this person?')">
                        @csrf @method('delete')<button class="btn-danger btn-sm">Remove</button>
                    </form>
                @endunless
            </li>
        @endforeach
    </ul>
    @error('user')<p role="alert">{{ $message }}</p>@enderror
    <form method="post" action="/admin/users" class="card">
        @csrf
        <div class="field">
            <label for="grant-email">Grant Admin to <span class="hint">Email of an existing user</span></label>
            <input id="grant-email" type="email" name="email" required>
            <x-error name="email" />
        </div>
        <button class="btn-primary">Grant</button>
    </form>
@endsection
