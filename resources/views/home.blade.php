@extends('layouts.app')

@section('content')
    <div class="card">
        <h2>Welcome to the Nail Polish Tracker</h2>
        <p>What would you like to do?</p>

        <ul style="list-style: none; padding-left: 0;">
            <li style="margin-bottom: 1rem;">
                <a href="{{ route('brands.index') }}" class="button">📦 Manage Brands</a>
            </li>
            <li style="margin-bottom: 1rem;">
                <a href="{{ route('polishes.index') }}" class="button">💅 View Nail Polishes</a>
            </li>
            <li style="margin-bottom: 1rem;">
                <a href="{{ route('locations.index') }}" class="button">📍 View Locations</a>
            </li>
            <li style="margin-bottom: 1rem;">
                <a href="{{ route('worn.recent') }}" class="button">🕒 Worn Recently</a>
            </li>
            <li style="margin-bottom: 1rem;">
                <a href="{{ route('worn.index') }}" class="button">📖 All Worn Looks</a>
            </li>
            <li style="margin-bottom: 1rem;">
                <a href="{{ route('worn.search') }}" class="button">❓ Have I Worn This?</a>
            </li>
        </ul>
    </div>
@endsection
