@extends('layouts.app')

@section('content')
    <div class="card">
        <h2>Locations</h2>

        @if(session('error'))
            <p class="highlight" style="background-color: #FFE082; color: #000;">{{ session('error') }}</p>
        @endif
        @if(session('success'))
            <p class="highlight">{{ session('success') }}</p>
        @endif


        <a href="{{ route('locations.create') }}" class="button">Add New Location</a>

        <table style="width: 100%; margin-top: 1rem;">
            <thead>
            <tr>
                <th style="text-align: left;">Name</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($locations as $location)
                <tr>
                    <td>{{ $location->name }}</td>
                    <td>
                        <a href="{{ route('locations.edit', $location) }}" class="button" style="background-color: #FFD700; color: #333; margin-right: 0.5rem;">Edit</a>
                        <form action="{{ route('locations.destroy', $location) }}" method="POST" style="display: inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="button" style="background-color: #E53935;">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="2">No locations yet.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div>
        <a href="{{ route('home') }}">Go back</a>
    </div>
@endsection
