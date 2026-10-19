@extends('layouts.app')

@section('title', $manicure->exists ? 'Edit manicure' : 'New manicure')

@section('content')
    <h1>{{ $manicure->exists ? 'Edit manicure' : 'New manicure' }}</h1>
    @if (session('status'))<p role="status">{{ session('status') }}</p>@endif

    @if ($manicure->exists)
        <h2>Photos</h2>
        <div style="display:flex;gap:1rem;flex-wrap:wrap">
            @foreach ($manicure->photos as $photo)
                <div>
                    <img src="/photos/{{ $photo->id }}/thumb?v={{ $photo->updated_at->timestamp }}" alt="Photo {{ $loop->iteration }}" width="160">
                    <form method="post" action="/manicure-photos/{{ $photo->id }}/rotate" style="display:inline">@csrf
                        <button name="direction" value="left">Rotate left</button>
                        <button name="direction" value="right">Rotate right</button>
                    </form>
                    @if ($manicure->photos->count() > 1)
                        <form method="post" action="/manicure-photos/{{ $photo->id }}" style="display:inline"
                              onsubmit="return confirm('Remove this photo?')">@csrf @method('delete')<button>Remove</button></form>
                    @endif
                </div>
            @endforeach
        </div>
        @error('photo')<p role="alert">{{ $message }}</p>@enderror
    @endif

    <form method="post" action="{{ $manicure->exists ? '/manicures/'.$manicure->id : '/manicures' }}" enctype="multipart/form-data">
        @csrf
        @if ($manicure->exists) @method('put') @endif

        @if (! $manicure->exists || $manicure->photos->count() < 4)
            <p><label>{{ $manicure->exists ? 'Add photos' : 'Photos (up to 4)' }}
                <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple @required(! $manicure->exists)></label></p>
        @endif
        <p><label>Date worn <input type="date" name="worn_on" value="{{ old('worn_on', $manicure->worn_on?->toDateString()) }}" required></label></p>
        <p><label>Caption <textarea name="caption" maxlength="500" rows="3">{{ old('caption', $manicure->caption) }}</textarea></label></p>
        <p><label>Who can see it
            <select name="visibility">
                <option value="">My usual setting ({{ auth()->user()->manicure_default_visibility }})</option>
                <option value="public" @selected(old('visibility', $manicure->visibility) === 'public')>Public</option>
                <option value="private" @selected(old('visibility', $manicure->visibility) === 'private')>Only me</option>
            </select></label></p>
        @if ($errors->any() && ! $errors->has('photo'))<ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
        <button>{{ $manicure->exists ? 'Save changes' : 'Save manicure' }}</button>
    </form>

    @if ($manicure->exists)
        <form method="post" action="/manicures/{{ $manicure->id }}" onsubmit="return confirm('Delete this manicure and its photos for good?')">
            @csrf @method('delete')<button>Delete this manicure</button>
        </form>
    @endif
@endsection
