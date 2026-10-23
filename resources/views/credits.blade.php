@extends('layouts.app')

@section('title', 'Photo credits')

@section('content')
    <h1>Photo credits</h1>
    <p>Some photos here were taken by other people and shared under open licenses. Thank you to everyone below.</p>
    <ul class="list">
        @forelse ($manicures as $manicure)
            <li>
                <a href="/manicures/{{ $manicure->id }}">{{ $manicure->credit }}</a>
                @if (Str::startsWith($manicure->credit_url, 'https://') || Str::startsWith($manicure->license_url, 'https://'))
                (@if (Str::startsWith($manicure->credit_url, 'https://'))<a href="{{ $manicure->credit_url }}" rel="noopener">source</a>@endif
                @if (Str::startsWith($manicure->credit_url, 'https://') && Str::startsWith($manicure->license_url, 'https://')) · @endif
                @if (Str::startsWith($manicure->license_url, 'https://'))<a href="{{ $manicure->license_url }}" rel="noopener">license</a>@endif)
            @endif
            </li>
        @empty
            <li>No credited photos right now.</li>
        @endforelse
    </ul>
@endsection
