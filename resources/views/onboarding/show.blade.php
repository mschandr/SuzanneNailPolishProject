@extends('layouts.app')

@section('title', 'Welcome')

@section('content')
    <p>Step {{ $step }} of {{ $total }}</p>
    <h1>{{ $content['title'] }}</h1>
    <p>{{ $content['body'] }}</p>
    <p><a href="{{ $content['link'] }}">{{ $content['cta'] }}</a></p>

    <nav style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap">
        @if ($step > 1)<a href="/welcome?step={{ $step - 1 }}">Back</a>@endif
        @if ($step < $total)
            <a href="/welcome?step={{ $step + 1 }}">Next</a>
        @else
            <form method="post" action="/welcome/complete" style="display:inline">@csrf<button>Finish</button></form>
        @endif
    </nav>

    @if ($step < $total)
        <form method="post" action="/welcome/complete" style="margin-top:1rem">@csrf<button>Skip the tour</button></form>
    @endif
    <p><small>You can come back to this any time from Help.</small></p>
@endsection
