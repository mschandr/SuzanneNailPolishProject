@extends('layouts.app')

@section('title', 'Admin')

@section('content')
    <h1>Admin</h1>
    @include('admin._nav')

    <div class="stats">
        <a href="/admin/moderation"><div class="card"><div class="stat">{{ $openReports }}</div>open {{ Str::plural('report', $openReports) }}<br><span class="muted">Moderation</span></div></a>
        <a href="/admin/needs-review"><div class="card">
            @if ($needsReview > 0)<div class="stat" aria-hidden="true">{{ $needsReview }}</div>
                <span class="sr-only">{{ $needsReview }} {{ $needsReview === 1 ? 'polish needs' : 'polishes need' }} review</span><span aria-hidden="true">{{ $needsReview === 1 ? 'polish needs' : 'polishes need' }} review</span>
            @else<div class="stat">0</div>nothing to review @endif<br><span class="muted">Needs review</span></div></a>
        <a href="/admin/invites"><div class="card"><div class="stat">{{ $inviteRequests }}</div>invite {{ Str::plural('request', $inviteRequests) }} waiting<br><span class="muted">Invites</span></div></a>
        <a href="/admin/suggestions"><div class="card"><div class="stat">{{ $suggestions }}</div>suggested {{ $suggestions === 1 ? 'polish' : 'polishes' }}<br><span class="muted">Suggestions</span></div></a>
    </div>

    <h2>Catalog</h2>
    <ul class="link-list">
        <li><a href="/admin/polishes">Find and edit a polish</a></li>
        <li><a href="/admin/polishes/create">Add a polish</a></li>
        <li><a href="/admin/imports">Import polishes</a></li>
        <li><a href="/admin/finishes">Finishes</a></li>
        <li><a href="/admin/color-families">Color families</a></li>
    </ul>
    <h2>People</h2>
    <ul class="link-list">
        <li><a href="/admin/invites">Invite requests</a></li>
        <li><a href="/admin/users">Admins</a></li>
        <li><a href="/admin/moderation/log">Moderation log</a></li>
    </ul>
@endsection
