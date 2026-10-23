@extends('layouts.app')

@section('title', 'Liked manicures')

@section('content')
    <h1>Manicures I've hearted</h1>
    @if ($manicures->total() === 0)
        <div class="empty">
            <p>You have not hearted any manicures yet. Browse the <a href="/gallery">Gallery</a> and tap Heart on the ones you love.</p>
            <a class="btn btn-primary" href="/gallery">Open the Gallery</a>
        </div>
    @else
        @include('gallery._tiles', ['manicures' => $manicures])
        {{ $manicures->links() }}
    @endif
@endsection
