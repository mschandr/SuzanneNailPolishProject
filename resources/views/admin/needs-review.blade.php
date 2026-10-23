@extends('layouts.app')

@section('title', 'Polishes that need review')

@section('content')
    <h1>Polishes that need review</h1>
    @include('admin._nav')
    <p>These were imported without a color family or a finish. Open one, fill in what's missing and save: it leaves this list.</p>
    <ul class="list">
        @forelse ($polishes as $polish)
            <li class="row">
                <span class="grow"><strong>{{ $polish->brand->name }}</strong> {{ $polish->name }}
                — missing {{ collect([$polish->color_family_id === null ? 'color family' : null, $polish->finishes_count === 0 ? 'finish' : null])->filter()->join(' and ') ?: 'nothing now: just save it' }}</span>
                <a class="btn btn-sm" href="/admin/polishes/{{ $polish->id }}/edit">Edit</a>
            </li>
        @empty
            <li>Nothing needs review.</li>
        @endforelse
    </ul>
    {{ $polishes->links() }}
@endsection
