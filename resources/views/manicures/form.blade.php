@extends('layouts.app')

@section('title', $manicure->exists ? 'Edit manicure' : 'New manicure')

@section('content')
    <div class="form-card wider">
        <h1>{{ $manicure->exists ? 'Edit manicure' : 'New manicure' }}</h1>
        @if (session('status'))<p role="status">{{ session('status') }}</p>@endif

        @if ($manicure->exists)
            <h2>Photos</h2>
            <div class="photo-edit">
                @foreach ($manicure->photos as $photo)
                    <div>
                        <img src="/photos/{{ $photo->id }}/thumb?v={{ $photo->updated_at->timestamp }}" alt="Photo {{ $loop->iteration }}" width="160" height="160">
                        <div class="btn-row">
                            <form method="post" action="/manicure-photos/{{ $photo->id }}/rotate" class="inline">@csrf
                                <button name="direction" value="left" class="btn-sm">↺ Rotate left</button>
                                <button name="direction" value="right" class="btn-sm">Rotate right ↻</button>
                            </form>
                            @if ($manicure->photos->count() > 1)
                                <form method="post" action="/manicure-photos/{{ $photo->id }}" class="inline"
                                      onsubmit="return confirm('Remove this photo?')">@csrf @method('delete')<button class="btn-danger btn-sm">Remove photo</button></form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            @error('photo')<p role="alert">{{ $message }}</p>@enderror
        @endif

        <form method="post" action="{{ $manicure->exists ? '/manicures/'.$manicure->id : '/manicures' }}" enctype="multipart/form-data" data-busy>
            @csrf
            @if ($manicure->exists) @method('put') @endif
            <x-errors :except="['photo']" />

            @if (! $manicure->exists || $manicure->photos->count() < 4)
                <div class="field">
                    <label for="photos">{{ $manicure->exists ? 'Add photos' : 'Photos (up to 4)' }}</label>
                    <input id="photos" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple @required(! $manicure->exists)>
                    <span class="hint">JPEG, PNG or WebP, up to 10 MB each. Location data is removed from every photo.</span>
                </div>
            @endif
            <div class="field">
                <label for="worn_on">Date worn</label>
                <input id="worn_on" type="date" name="worn_on" value="{{ old('worn_on', $manicure->worn_on?->toDateString()) }}" required>
            </div>
            <div class="field">
                <label for="caption">Caption <span class="hint">Optional, up to 500 characters</span></label>
                <textarea id="caption" name="caption" maxlength="500" rows="3">{{ old('caption', $manicure->caption) }}</textarea>
            </div>
            <div class="field">
                <label for="visibility">Who can see it</label>
                <select id="visibility" name="visibility">
                    <option value="">My usual setting ({{ auth()->user()->manicure_default_visibility }})</option>
                    <option value="public" @selected(old('visibility', $manicure->visibility) === 'public')>Public</option>
                    <option value="private" @selected(old('visibility', $manicure->visibility) === 'private')>Only me</option>
                </select>
            </div>
            <div class="actions">
                <button class="btn-primary">{{ $manicure->exists ? 'Save changes' : 'Save manicure' }}</button>
                <a class="btn" href="{{ $manicure->exists ? '/manicures/'.$manicure->id : '/manicures' }}">Cancel</a>
            </div>
            <p class="busy-note" hidden>Uploading… this can take a minute on mobile data. Please don't close the page.</p>
        </form>

        @if ($manicure->exists)
            <div class="danger-zone">
                <h2>Delete this manicure</h2>
                <p>This removes the manicure and all its photos for good.</p>
                <form method="post" action="/manicures/{{ $manicure->id }}" onsubmit="return confirm('Delete this manicure and its photos for good?')">
                    @csrf @method('delete')<button class="btn-danger">Delete this manicure</button>
                </form>
            </div>
        @endif
    </div>
@endsection
