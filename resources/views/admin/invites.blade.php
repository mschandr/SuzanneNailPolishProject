@extends('layouts.app')

@section('content')
    <h1>Invite requests</h1>
    <ul>
        @forelse ($requests as $request)
            <li>
                {{ $request->email }}
                @if ($request->invited_at) (invited {{ $request->invited_at->toDateString() }}) @endif
                <form method="post" action="/admin/invites" style="display:inline">
                    @csrf<input type="hidden" name="email" value="{{ $request->email }}">
                    <button>{{ $request->invited_at ? 'Send again' : 'Send invite' }}</button>
                </form>
            </li>
        @empty
            <li>No requests.</li>
        @endforelse
    </ul>
    <h2>Invite someone directly</h2>
    <form method="post" action="/admin/invites">
        @csrf
        <label>Email <input type="email" name="email" required></label>
        @error('email')<p>{{ $message }}</p>@enderror
        <button>Send invite</button>
    </form>
@endsection
