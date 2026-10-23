@extends('layouts.app')

@section('title', $person->display_name)

@section('content')
    <h1>{{ $person->display_name }}</h1>
    @if ($viewerIsOwner)
        <div class="actions mt-0">
            <a class="btn btn-primary" href="/manicures/create">New manicure</a>
            <a href="/settings/manicures">Privacy setting</a>
        </div>
    @endif
    @include('manicures._grid', ['manicures' => $manicures, 'showPrivacy' => $viewerIsOwner])
    {{ $manicures->links() }}
@endsection
