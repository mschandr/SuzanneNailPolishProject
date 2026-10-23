@extends('layouts.app')

@section('title', 'Moderation log')

@section('content')
    <h1>Moderation log</h1>
    @include('admin._nav')
    <p><a href="/admin/moderation">Back to moderation</a></p>
    <ul class="list">
        @forelse ($entries as $entry)
            <li>{{ $entry->created_at->toDayDateTimeString() }} — {{ $entry->admin?->display_name ?? 'a former Admin' }}: {{ $entry->summary }}@if ($entry->note) ({{ $entry->note }})@endif</li>
        @empty
            <li>Nothing yet.</li>
        @endforelse
    </ul>
    {{ $entries->links() }}
@endsection
