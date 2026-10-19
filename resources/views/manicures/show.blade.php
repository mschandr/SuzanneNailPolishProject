@extends('layouts.app')

@section('title', 'Manicure by '.$manicure->user->display_name)

@section('content')
    <p><a href="/people/{{ $manicure->user_id }}">{{ $manicure->user->display_name }}</a> · {{ $manicure->worn_on->toFormattedDateString() }}
        @if ($isOwner)· {{ $manicure->isPublic() ? 'Public' : 'Only you' }}@endif</p>
    @if (session('status'))<p role="status">{{ session('status') }}</p>@endif
    @if ($manicure->caption)<p>{{ $manicure->caption }}</p>@endif

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(14rem,1fr));gap:.5rem">
        @foreach ($manicure->photos as $photo)
            <img src="/photos/{{ $photo->id }}/full?v={{ $photo->updated_at->timestamp }}" alt="Manicure photo {{ $loop->iteration }}" style="width:100%;height:auto">
        @endforeach
    </div>
    <p>
        {{ $heartCount }} {{ Str::plural('heart', $heartCount) }}
        @auth
            @unless ($isOwner)
                @if ($hearted)
                    <form method="post" action="/manicures/{{ $manicure->id }}/heart" style="display:inline">@csrf @method('delete')<button>Remove heart</button></form>
                @else
                    <form method="post" action="/manicures/{{ $manicure->id }}/heart" style="display:inline">@csrf<button>Heart this</button></form>
                @endif
            @endunless
        @else
            · <a href="/login">Sign in to heart</a>
        @endauth
    </p>
    @error('heart')<p role="alert">{{ $message }}</p>@enderror

    <h2>Polishes worn</h2>
    @if ($manicure->layers->isNotEmpty())
        <ol>
            @foreach ($manicure->layers as $layer)
                @php($polish = $layer->polish)
                <li>
                    {{-- A private polish keeps its page private: strangers see its name but no link. --}}
                    @if (! $polish->isPrivate() || $isOwner)
                        <a href="{{ $polish->url() }}"><strong>{{ $polish->brand->name }}</strong> {{ $polish->name }}</a>
                    @else
                        <strong>{{ $polish->brand->name }}</strong> {{ $polish->name }}
                    @endif
                    @if ($isOwner && $layer->bottle_id)<small>(from my collection)</small>@endif
                </li>
            @endforeach
        </ol>
    @elseif ($isOwner)
        <p>Add the polishes you wore so others can see what it looks like.</p>
    @endif
    @if ($isOwner)<p><a href="/manicures/{{ $manicure->id }}/polishes">{{ $manicure->layers->isEmpty() ? 'Add polishes' : 'Edit polishes' }}</a> · <a href="/manicures/{{ $manicure->id }}/edit">Edit</a></p>@endif
@endsection
