@extends('layouts.app')

@section('title', 'Liked manicures')

@section('content')
    <h1>Manicures I've hearted</h1>
    @if ($manicures->total() === 0)
        <p>You have not hearted any manicures yet. Browse the <a href="/gallery">Gallery</a> and tap Heart on the ones you love.</p>
    @else
        @include('gallery._tiles', ['manicures' => $manicures])
        {{ $manicures->links() }}
    @endif
@endsection
