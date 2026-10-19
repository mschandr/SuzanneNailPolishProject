@extends('layouts.app')

@section('content')
    <h1>Admin</h1>
    <ul>
        <li><a href="/admin/polishes/create">Add a polish</a></li>
        <li><a href="/admin/imports">Import polishes</a></li>
        <li><a href="/admin/needs-review">Needs review</a>@if ($needsReview > 0) — {{ $needsReview }} {{ $needsReview === 1 ? 'polish needs' : 'polishes need' }} review @endif</li>
        <li><a href="/admin/moderation">Moderation</a></li>
        <li><a href="/admin/suggestions">Suggested polishes</a></li>
        <li><a href="/admin/invites">Invite requests</a></li>
        <li><a href="/admin/users">Admins</a></li>
        <li><a href="/admin/finishes">Finishes</a></li>
        <li><a href="/admin/color-families">Color families</a></li>
    </ul>
@endsection
