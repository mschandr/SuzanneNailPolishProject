@extends('layouts.app')

@section('title', 'My manicures')

@section('content')
    <h1>My manicures</h1>
    @if (session('status'))<p role="status">{{ session('status') }}</p>@endif
    <p><a href="/manicures/create">New manicure</a> · <a href="/settings/manicures">Privacy setting</a></p>
    @include('manicures._grid', ['manicures' => $manicures, 'showPrivacy' => true])
    {{ $manicures->links() }}
@endsection
