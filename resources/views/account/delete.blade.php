@extends('layouts.app')

@section('title', 'Delete my account')

@section('content')
    <div class="form-card">
        <h1>Delete my account</h1>

        @if ($lastAdmin)
            <p role="alert">You are the last Admin, so you can't delete your account yet. Make someone else an Admin first.</p>
            <p><a href="/admin/users">Manage Admins</a></p>
        @else
            <p><strong>This is permanent and can't be undone.</strong> Deleting your account removes:</p>
            <ul>
                @foreach ($counts as $label => $count)
                    <li>{{ $count }} {{ Str::plural($label, $count) }}</li>
                @endforeach
                <li>{{ $photos }} {{ Str::plural('photo', $photos) }}</li>
                <li>Every manicure you shared, with its hearts and reports, and the hearts you gave others</li>
                <li>Your sign-in history, invitations and requests tied to your email address</li>
            </ul>
            <p>Polishes the Admin added to the public Catalog from your suggestions stay there, with no link to you.</p>

            <form method="post" action="/account/delete" class="danger-zone">
                @csrf
                <div class="field">
                    <label for="confirmation">Type <strong>DELETE</strong> to confirm</label>
                    <input id="confirmation" name="confirmation" autocomplete="off" required @error('confirmation') aria-invalid="true" aria-describedby="confirmation-error" @enderror>
                    @error('confirmation')<p class="field-error" id="confirmation-error" role="alert">{{ $message }}</p>@enderror
                </div>
                <button class="btn-danger">Permanently delete my account</button>
            </form>
        @endif
        <p class="mt-lg"><a class="btn btn-primary" href="/home">Keep my account</a></p>
    </div>
@endsection
