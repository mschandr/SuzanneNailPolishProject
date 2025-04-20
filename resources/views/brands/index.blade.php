@extends('layouts.app')

@section('content')
    <div class="card">
        <h2>Brands</h2>

        @if(session('success'))
            <p class="highlight">{{ session('success') }}</p>
        @endif

        <a href="{{ route('brands.create') }}" class="button">Add New Brand</a>

        <table style="width: 100%; margin-top: 1rem;">
            <thead>
            <tr>
                <th style="text-align: left;">Name</th>
                <th style="text-align: left;">Total</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($brands as $brand)
                <tr>
                    <td>{{ $brand->name }}</td>
                    <td>{{ $brand->total }}</td>
                    <td>
                        <a href="{{ route('brands.edit', $brand) }}" class="button" style="background-color: #FFD700; color: #333; margin-right: 0.5rem;">Edit</a>
                        <form action="{{ route('brands.destroy', $brand) }}" method="POST" style="display: inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="button" style="background-color: #E53935;">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">No brands yet.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
