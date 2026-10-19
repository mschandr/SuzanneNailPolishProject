@extends('layouts.app')

@section('title', 'Manicure by '.$manicure->user->display_name)

@section('content')
    <p><a href="/people/{{ $manicure->user_id }}">{{ $manicure->user->display_name }}</a> · {{ $manicure->worn_on->toFormattedDateString() }}
        @if ($isOwner)· {{ $manicure->hidden_at ? 'Hidden by a moderator' : ($manicure->isPublic() ? 'Public' : 'Only you') }}@endif</p>
    @if (session('status'))<p role="status">{{ session('status') }}</p>@endif
    @if ($manicure->hidden_at && ($isOwner || auth()->user()?->is_admin))
        <p role="alert"><strong>This manicure has been hidden by a moderator</strong> and can't be seen by others.
            @if ($manicure->hidden_reason)Reason: {{ $manicure->hidden_reason }}.@endif
            @if ($isOwner)You can still delete it.@endif</p>
    @endif
    @if ($manicure->caption)<p>{{ $manicure->caption }}</p>@endif

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(14rem,1fr));gap:.5rem">
        @foreach ($manicure->photos as $photo)
            <img src="/photos/{{ $photo->id }}/full?v={{ $photo->updated_at->timestamp }}" alt="Manicure photo {{ $loop->iteration }}" style="width:100%;height:auto">
        @endforeach
    </div>
    @if ($manicure->credit)
        <p><small>Photo credit: {{ $manicure->credit }}
            @if (Str::startsWith($manicure->credit_url, 'https://') || Str::startsWith($manicure->license_url, 'https://'))
                (@if (Str::startsWith($manicure->credit_url, 'https://'))<a href="{{ $manicure->credit_url }}" rel="noopener">source</a>@endif
                @if (Str::startsWith($manicure->credit_url, 'https://') && Str::startsWith($manicure->license_url, 'https://')) · @endif
                @if (Str::startsWith($manicure->license_url, 'https://'))<a href="{{ $manicure->license_url }}" rel="noopener">license</a>@endif)
            @endif</small></p>
    @endif

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

    @auth
        @unless ($isOwner)
            @if ($reported)
                <p><small>You reported this manicure. Thank you.</small></p>
            @else
                <details>
                    <summary>Report this manicure</summary>
                    <form method="post" action="/manicures/{{ $manicure->id }}/report">
                        @csrf
                        <label>Why? <select name="reason" required>
                            @foreach (\App\Models\Report::REASONS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select></label>
                        <label>Details (optional) <textarea name="details" maxlength="1000" rows="3"></textarea></label>
                        <button>Send report</button>
                    </form>
                </details>
            @endif
            @error('reason')<p role="alert">{{ $message }}</p>@enderror
            @error('report')<p role="alert">{{ $message }}</p>@enderror
        @endunless
    @else
        <p><small><a href="/login">Sign in to report</a></small></p>
    @endauth

    <h2>Polishes worn</h2>
    @php($active = $manicure->activeLayers())
    @if ($active->isNotEmpty())
        @if ($manicure->polish_mode === \App\Models\Manicure::MODE_NAILS)
            @foreach (\App\Models\Manicure::NAILS as $key => $label)
                @php($onNail = $active->where('target', $key))
                @if ($onNail->isNotEmpty())
                    <h3>{{ $label }}</h3>
                    <ol>@foreach ($onNail as $layer)<li>@include('manicures._layer', ['layer' => $layer])</li>@endforeach</ol>
                @endif
            @endforeach
        @else
            <ol>@foreach ($active as $layer)<li>@include('manicures._layer', ['layer' => $layer])</li>@endforeach</ol>
        @endif
    @elseif ($isOwner)
        <p>Add the polishes you wore so others can see what it looks like.</p>
    @endif
    @if ($isOwner)<p><a href="/manicures/{{ $manicure->id }}/polishes">{{ $active->isEmpty() ? 'Add polishes' : 'Edit polishes' }}</a> · <a href="/manicures/{{ $manicure->id }}/edit">Edit</a></p>@endif
@endsection
