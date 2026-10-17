@extends('layouts.app')

@section('content')
    <h1>Admins</h1>
    <ul>
        @foreach ($admins as $admin)
            <li>
                {{ $admin->display_name ?? $admin->email }} ({{ $admin->email }})
                @unless ($admin->is(auth()->user()))
                    <form method="post" action="/admin/users/{{ $admin->id }}" style="display:inline">
                        @csrf @method('delete')<button>Remove</button>
                    </form>
                @endunless
            </li>
        @endforeach
    </ul>
    @error('user')<p>{{ $message }}</p>@enderror
    <form method="post" action="/admin/users">
        @csrf
        <label>Grant Admin to (email of an existing user) <input type="email" name="email" required></label>
        @error('email')<p>{{ $message }}</p>@enderror
        <button>Grant</button>
    </form>
@endsection
