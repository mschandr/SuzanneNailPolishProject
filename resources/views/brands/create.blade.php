@extends('layouts.app')

@section('content')
    <div class="card">
        <h2>Add New Brand</h2>

        @if ($errors->any())
            <div class="highlight">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('brands.store') }}" method="POST">
            @csrf
            <label for="name">Brand Name:</label><br>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required><br><br>
            <button type="submit" class="button">Add Brand</button>
        </form>
    </div>
@endsection
