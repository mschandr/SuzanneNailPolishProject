@extends('layouts.app')

@section('title', 'Invite requests')

@section('content')
    <h1>Invite requests</h1>
    @include('admin._nav')
    <ul class="list">
        @forelse ($requests as $request)
            <li class="row">
                <span class="grow">{{ $request->email }}
                @if ($request->invited_at) <span class="badge">invited {{ $request->invited_at->toDateString() }}</span> @endif</span>
                <form method="post" action="/admin/invites">
                    @csrf<input type="hidden" name="email" value="{{ $request->email }}">
                    <button class="btn-sm">{{ $request->invited_at ? 'Send again' : 'Send invite' }}</button>
                </form>
            </li>
        @empty
            <li>No requests.</li>
        @endforelse
    </ul>
    <h2>Invite someone directly</h2>
    <form method="post" action="/admin/invites" class="card">
        @csrf
        <div class="field">
            <label for="invite-email">Email</label>
            <input id="invite-email" type="email" name="email" required>
            <x-error name="email" />
        </div>
        <button class="btn-primary">Send invite</button>
    </form>
@endsection
