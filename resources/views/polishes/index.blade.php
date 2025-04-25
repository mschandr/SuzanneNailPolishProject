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


        <a href="{{ route('polishes.create') }}" class="button">Add New Polish</a>

        <table style="width: 100%; margin-top: 1rem;">
            <thead>
            <tr>
                <th style="text-align: left;">Name</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($polishes as $polish)
                <tr>
                    <td>
                        @if($polish->is_available_online)
                            <a href="{{ $polish->product_url }}"  target="_blank" rel="noopener noreferrer">
                                {{ $polish->name }}
                            </a>
                        @else
                            {{ $polish->name }}
                        @endif
                    </td>
                    <td>{{ $polish->brand->name }}</td>
                    <td>{{ $polish->location->name }}</td>
                    <td>{{ $polish->shade }}</td>
                    <td>{{ ucfirst($polish->polish_type) }}</td>
                    <td>{{ $polish->collection }}</td>
                    <td>{{ $polish->release_date }}</td>
                    <td>
                        <a href="{{ route('polishes.edit', $polish) }}" class="button" style="background-color: #FFD700; color: #333; margin-right: 0.5rem;">Edit</a>
                        <form action="{{ route('polishes.destroy', $polish) }}" method="POST" style="display: inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="button" style="background-color: #E53935;">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="2">No Polishes yet.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
