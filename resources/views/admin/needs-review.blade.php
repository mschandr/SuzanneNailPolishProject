@extends('layouts.app')

@section('title', 'Polishes that need review')

@section('content')
    <p><a href="/admin">Admin</a></p>
    <h1>Polishes that need review</h1>
    <p>These were imported without a colour family or a finish. Open one, fill in what's missing and save: it leaves this list.</p>
    <ul>
        @forelse ($polishes as $polish)
            <li>
                <strong>{{ $polish->brand->name }}</strong> {{ $polish->name }}
                — missing {{ collect([$polish->color_family_id === null ? 'colour family' : null, $polish->finishes_count === 0 ? 'finish' : null])->filter()->join(' and ') ?: 'nothing now: just save it' }}
                · <a href="/admin/polishes/{{ $polish->id }}/edit">Edit</a>
            </li>
        @empty
            <li>Nothing needs review.</li>
        @endforelse
    </ul>
    {{ $polishes->links() }}
@endsection
