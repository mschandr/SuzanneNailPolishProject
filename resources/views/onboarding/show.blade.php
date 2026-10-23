@extends('layouts.app')

@section('title', 'Welcome')

@section('content')
    <div class="form-card">
        <p class="muted">Step {{ $step }} of {{ $total }}</p>
        <h1>{{ $content['title'] }}</h1>
        <p>{{ $content['body'] }}</p>
        <p><a class="btn btn-primary" href="{{ $content['link'] }}">{{ $content['cta'] }}</a></p>

        <nav class="actions" aria-label="Tour steps">
            @if ($step > 1)<a class="btn" href="/welcome?step={{ $step - 1 }}">Back</a>@endif
            @if ($step < $total)
                <a class="btn" href="/welcome?step={{ $step + 1 }}">Next</a>
            @else
                <form method="post" action="/welcome/complete" class="inline">@csrf<button class="btn-primary">Finish</button></form>
            @endif
        </nav>

        @if ($step < $total)
            <form method="post" action="/welcome/complete" class="mt-md">@csrf<button class="btn-link">Skip the tour</button></form>
        @endif
        <p><small>You can come back to this any time from Help.</small></p>
    </div>
@endsection
