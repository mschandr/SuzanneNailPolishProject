@extends('layouts.app')

@section('content')
    <div class="card">
        <h2>Add New Polish</h2>

        @if ($errors->any())
            <div class="highlight">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('polishes.store') }}" method="POST">
            @csrf

            <label for="name">Polish Name:</label><br>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required><br><br>

            <label for="shade">Shade:</label><br>
            <select name="shade" id="shade" required>
                <option value="">Select Shade</option>
                @foreach(['Pink', 'Red', 'Blue', 'Green', 'Purple', 'Orange', 'Yellow', 'Black', 'White', 'Grey'] as $shade)
                    <option value="{{ $shade }}" {{ old('shade') == $shade ? 'selected' : '' }}>{{ $shade }}</option>
                @endforeach
            </select><br><br>

            <label for="brand_id">Brand:</label><br>
            <select name="brand_id" id="brand_id" required>
                <option value="">Select Brand</option>
                @foreach($brands as $brand)
                    <option value="{{ $brand->id }}" {{ old('brand_id') == $brand->id ? 'selected' : '' }}>
                        {{ $brand->name }}
                    </option>
                @endforeach
            </select><br><br>

            <label for="location_id">Location:</label><br>
            <select name="location_id" id="location_id">
                <option value="">Select Location (optional)</option>
                @foreach($locations as $location)
                    <option value="{{ $location->id }}" {{ old('location_id') == $location->id ? 'selected' : '' }}>
                        {{ $location->name }}
                    </option>
                @endforeach
            </select><br><br>

            <label for="polish_collection_id">Collection (optional):</label><br>
            <select name="polish_collection_id" id="polish_collection_id">
                <option value="">(None)</option>
                @foreach($polish_collections as $collection)
                    <option value="{{ $collection->id }}" {{ old('polish_collection_id') == $collection->id ? 'selected' : '' }}>
                        {{ $collection->name }}
                    </option>
                @endforeach
            </select><br><br>

            <label for="polish_type">Polish type:</label><br/>
            <select name="polish_type" id="polish_type">
                <option value="polish">Default (regular nail polish)</option>
                <option value="base_coat">Base coat</option>
                <option value="topper">Nail polish topper</option>
                <option value="top_coat">Top coat</option>
            </select><br/><br/>
            <button type="submit" class="button">Save Polish</button>
        </form>
    </div>
@endsection
