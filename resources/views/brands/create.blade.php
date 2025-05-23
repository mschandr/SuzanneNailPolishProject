@extends('layouts.app')

@section('content')
    <div class="card">
        <h2>Add New Location</h2>

        @if ($errors->any())
            <div class="highlight">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('locations.store') }}" method="POST">
            @csrf
            <label for="name">Brand Name:</label><br>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required><br><br>
            <button type="submit" class="button">Add location</button>
            <a href="{{ route('locations.index') }}" class="button" style="background-color: #ccc; color: #333; margin-top: 1rem; display: inline-block;">← Back to Brand List</a>
        </form>
    </div>
@endsection
