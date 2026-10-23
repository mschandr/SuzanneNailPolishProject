@extends('layouts.app')

@section('title', 'Moderation')

@section('content')
    <h1>Moderation</h1>
    @include('admin._nav')
    <p><a href="/admin/moderation/log">Action log</a></p>
    @if (session('status'))<p role="status">{{ session('status') }}</p>@endif
    @error('suspend')<p role="alert">{{ $message }}</p>@enderror

    <h2>Open reports</h2>
    @forelse ($queue as $manicure)
        <section class="card mod-card">
            <p>
                <a href="/manicures/{{ $manicure->id }}"><strong>Manicure by {{ $manicure->user->display_name }}</strong></a>
                · {{ $manicure->open_reports_count }} {{ Str::plural('report', $manicure->open_reports_count) }}
            </p>
            @if ($manicure->caption)<p>"{{ $manicure->caption }}"</p>@endif
            <ul>
                @foreach ($manicure->reports as $report)
                    <li>{{ \App\Models\Report::REASONS[$report->reason] ?? $report->reason }} — {{ $report->reporter->display_name }}
                        @if ($report->details): {{ $report->details }}@endif</li>
                @endforeach
            </ul>
            <div class="mod-actions">
                <form method="post" action="/admin/reports/{{ $manicure->id }}/dismiss">@csrf<button class="btn-sm">Dismiss reports</button></form>
                <form method="post" action="/admin/manicures/{{ $manicure->id }}/hide" class="form-row">@csrf
                    <div class="field"><label for="hide-{{ $manicure->id }}">Reason for hiding <span class="hint">Optional</span></label><input id="hide-{{ $manicure->id }}" name="reason" maxlength="300"></div>
                    <button class="btn-sm">Hide</button></form>
            </div>
            <div class="mod-danger">
                <form method="post" action="/admin/manicures/{{ $manicure->id }}"
                      onsubmit="return confirm('Delete this manicure and its photos permanently?')">@csrf @method('delete')
                    <button class="btn-danger btn-sm">Delete manicure permanently</button></form>
                @unless ($manicure->user->is_admin)
                    <form method="post" action="/admin/users/{{ $manicure->user_id }}/suspend" class="form-row"
                          onsubmit="return confirm('Suspend {{ $manicure->user->display_name }}?')">@csrf
                        <div class="field"><label for="suspend-{{ $manicure->id }}">Reason for suspending <span class="hint">Optional</span></label><input id="suspend-{{ $manicure->id }}" name="reason" maxlength="300"></div>
                        <button class="btn-danger btn-sm">Suspend {{ $manicure->user->display_name }}</button></form>
                @endunless
            </div>
        </section>
    @empty
        <div class="empty"><p>No open reports.</p></div>
    @endforelse

    <h2>Hidden manicures</h2>
    <ul class="list">
        @forelse ($hidden as $manicure)
            <li class="row">
                <span class="grow"><a href="/manicures/{{ $manicure->id }}">Manicure by {{ $manicure->user->display_name }}</a>
                — hidden {{ $manicure->hidden_at->toDayDateTimeString() }}@if ($manicure->hidden_reason): {{ $manicure->hidden_reason }}@endif</span>
                <form method="post" action="/admin/manicures/{{ $manicure->id }}/restore">@csrf<button class="btn-sm">Restore</button></form>
            </li>
        @empty
            <li>None.</li>
        @endforelse
    </ul>

    <h2>Suspended users</h2>
    <ul class="list">
        @forelse ($suspended as $user)
            <li class="row">
                <span class="grow">{{ $user->display_name }} — since {{ $user->suspended_at->toDayDateTimeString() }}@if ($user->suspended_reason): {{ $user->suspended_reason }}@endif</span>
                <form method="post" action="/admin/users/{{ $user->id }}/unsuspend">@csrf<button class="btn-sm">Unsuspend</button></form>
            </li>
        @empty
            <li>None.</li>
        @endforelse
    </ul>
@endsection
