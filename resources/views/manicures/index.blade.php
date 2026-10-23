@extends('layouts.app')

@section('title', 'My manicures')

@section('content')
    <h1>My manicures</h1>
    @if (session('status'))<p role="status">{{ session('status') }}</p>@endif
    <div class="actions mt-0">
        <a class="btn btn-primary" href="/manicures/create">New manicure</a>
        <a href="/settings/manicures">Privacy setting</a>
    </div>
    @include('manicures._grid', ['manicures' => $manicures, 'showPrivacy' => true])
    {{ $manicures->links() }}
@endsection
