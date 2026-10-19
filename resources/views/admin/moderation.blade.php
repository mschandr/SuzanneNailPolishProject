@extends('layouts.app')

@section('title', 'Moderation')

@section('content')
    <h1>Moderation</h1>
    <p><a href="/admin/moderation/log">Action log</a></p>
    @if (session('status'))<p role="status">{{ session('status') }}</p>@endif
    @error('suspend')<p role="alert">{{ $message }}</p>@enderror

    <h2>Open reports</h2>
    @forelse ($queue as $manicure)
        <section style="border-top:1px solid #ddd;padding:.5rem 0">
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
            <form method="post" action="/admin/reports/{{ $manicure->id }}/dismiss" style="display:inline">@csrf<button>Dismiss</button></form>
            <form method="post" action="/admin/manicures/{{ $manicure->id }}/hide" style="display:inline">@csrf
                <input name="reason" placeholder="Reason (optional)" maxlength="300"><button>Hide</button></form>
            <form method="post" action="/admin/manicures/{{ $manicure->id }}" style="display:inline"
                  onsubmit="return confirm('Delete this manicure and its photos permanently?')">@csrf @method('delete')
                <button>Delete</button></form>
            @unless ($manicure->user->is_admin)
                <form method="post" action="/admin/users/{{ $manicure->user_id }}/suspend" style="display:inline"
                      onsubmit="return confirm('Suspend {{ $manicure->user->display_name }}?')">@csrf
                    <input name="reason" placeholder="Reason (optional)" maxlength="300"><button>Suspend {{ $manicure->user->display_name }}</button></form>
            @endunless
        </section>
    @empty
        <p>No open reports.</p>
    @endforelse

    <h2>Hidden manicures</h2>
    <ul>
        @forelse ($hidden as $manicure)
            <li>
                <a href="/manicures/{{ $manicure->id }}">Manicure by {{ $manicure->user->display_name }}</a>
                — hidden {{ $manicure->hidden_at->toDayDateTimeString() }}@if ($manicure->hidden_reason): {{ $manicure->hidden_reason }}@endif
                <form method="post" action="/admin/manicures/{{ $manicure->id }}/restore" style="display:inline">@csrf<button>Restore</button></form>
            </li>
        @empty
            <li>None.</li>
        @endforelse
    </ul>

    <h2>Suspended users</h2>
    <ul>
        @forelse ($suspended as $user)
            <li>
                {{ $user->display_name }} — since {{ $user->suspended_at->toDayDateTimeString() }}@if ($user->suspended_reason): {{ $user->suspended_reason }}@endif
                <form method="post" action="/admin/users/{{ $user->id }}/unsuspend" style="display:inline">@csrf<button>Unsuspend</button></form>
            </li>
        @empty
            <li>None.</li>
        @endforelse
    </ul>
@endsection
