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
    @if ($isOwner)<p><a href="/manicures/{{ $manicure->id }}/edit">Edit</a></p>@endif
@endsection
