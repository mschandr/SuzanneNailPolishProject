@extends('layouts.app')

@section('content')
    <div class="card">
        <h2>Edit Brand</h2>

        @if ($errors->any())
            <div class="highlight">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('locations.update', $location) }}" method="POST">
            @csrf
            @method('PUT')
            <label for="name">Location Name:</label><br>
            <input type="text" name="name" id="name" value="{{ old('name', $location->name) }}" required><br><br>
            <button type="submit" class="button">Update Location</button>
        </form>
    </div>
@endsection
