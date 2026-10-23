@extends('layouts.app')

@section('title', 'Import preview')

@section('content')
    @php($label = ['new' => 'New', 'exists' => 'Already in the Catalog', 'duplicate' => 'Duplicate', 'invalid' => 'Problem', 'added' => 'Added', 'skipped' => 'Skipped'])
    <p class="breadcrumb"><a href="/admin/imports">All imports</a></p>
    <h1>{{ $import->filename }}</h1>
    @include('admin._nav')
    @if (session('status'))<p role="status">{{ session('status') }}</p>@endif
    @error('rows')<p role="alert">{{ $message }}</p>@enderror

    <p>
        {{ $counts['new'] ?? 0 }} new ·
        {{ $counts['exists'] ?? 0 }} already in the Catalog ·
        {{ $counts['duplicate'] ?? 0 }} {{ Str::plural('duplicate', $counts['duplicate'] ?? 0) }} ·
        {{ $counts['invalid'] ?? 0 }} {{ Str::plural('problem', $counts['invalid'] ?? 0) }}
        @if (($counts['added'] ?? 0) > 0) · {{ $counts['added'] }} added @endif
        @if (($counts['skipped'] ?? 0) > 0) · {{ $counts['skipped'] }} skipped @endif
    </p>

    @if ($import->status === 'applied')
        <p><strong>Applied {{ $import->applied_at->toDayDateTimeString() }}: {{ $import->applied_count }} added.</strong></p>
    @elseif ($import->applied_count > 0)
        <p>{{ $import->applied_count }} added so far. Tick the rest to add them, or leave them: this page keeps for a week.</p>
    @else
        <p>Tick the polishes to add. Nothing has been added yet.</p>
    @endif

    @if ($import->status === 'preview')
    <form method="post" action="/admin/imports/{{ $import->id }}/apply" id="apply">@csrf</form>
    @endif

    <div class="table-wrap"><table>
        <thead><tr><th><span class="sr-only">Add</span></th><th>Line</th><th>Brand</th><th>Shade</th><th>Details</th><th>Result</th></tr></thead>
        <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>@if ($import->status === 'preview' && $row->status === 'new')<input type="checkbox" name="rows[]" value="{{ $row->id }}" form="apply" checked aria-label="Add line {{ $row->line }}">@endif</td>
                <td>{{ $row->line }}</td>
                <td>{{ $row->brand }}</td>
                <td>{{ $row->name }}</td>
                <td>{{ collect([$row->collection, $row->color_family, $row->finishes])->filter()->join(' · ') }}</td>
                <td><strong>{{ $label[$row->status] }}</strong> {{ $row->problems }}</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>

    @if ($import->status === 'preview')
        <p>
            <button type="button" class="btn-sm" onclick="document.querySelectorAll('input[name=&quot;rows[]&quot;]').forEach(c => c.checked = true)">Tick all</button>
            <button type="button" class="btn-sm" onclick="document.querySelectorAll('input[name=&quot;rows[]&quot;]').forEach(c => c.checked = false)">Tick none</button>
            <button form="apply" class="btn-primary">Add the ticked polishes to the Catalog</button>
        </p>
    @endif
@endsection
