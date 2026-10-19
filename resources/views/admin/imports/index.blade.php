@extends('layouts.app')

@section('title', 'Import polishes')

@section('content')
    <h1>Import polishes</h1>
    <p>Add many polishes to the Catalog from a spreadsheet. You'll see a preview and choose what to add; nothing changes until you confirm.</p>

    <p><strong>Only import information you have the right to use.</strong> Many shops' terms forbid copying their product data automatically, and photos belong to whoever took them. Image columns are stored as links and are never copied.</p>

    <h2>Upload a file</h2>
    <p>A CSV file (in your spreadsheet: <em>Save as CSV UTF-8</em>), up to {{ \App\Models\Import::MAX_ROWS }} rows. The first row names the columns. Only <code>brand</code> and <code>shade</code> are required:</p>
    <p><code>brand, shade, collection, color_family, finishes, vendor_url, image_url</code></p>
    <ul>
        <li><strong>finishes</strong>: separate several with a semicolon, e.g. <code>Creme;Shimmer</code>. Names must match the Finish list.</li>
        <li><strong>color_family</strong>: must match the Color family list. Anything that doesn't match is left blank and the polish is flagged for review.</li>
        <li><strong>vendor_url</strong>: a link to buy it. <strong>image_url</strong>: an https link to a picture.</li>
        <li>Polishes already in the Catalog (same brand and shade) are never changed.</li>
    </ul>
    <p><a href="/admin/imports/template.csv">Download a template</a></p>

    <form method="post" action="/admin/imports" enctype="multipart/form-data">
        @csrf
        <label>File <input type="file" name="file" accept=".csv,text/csv,text/plain" required></label>
        @error('file')<p role="alert">{{ $message }}</p>@enderror
        <button>Upload and preview</button>
    </form>

    <h2>Recent imports</h2>
    <ul>
        @forelse ($imports as $import)
            <li>
                <a href="/admin/imports/{{ $import->id }}">{{ $import->filename }}</a>
                — {{ $import->created_at->toDayDateTimeString() }}, {{ $import->total }} {{ Str::plural('row', $import->total) }},
                @if ($import->status === 'applied') added {{ $import->applied_count }}@else waiting for you to confirm @endif
            </li>
        @empty
            <li>None yet.</li>
        @endforelse
    </ul>
    <p><a href="/admin/needs-review">Polishes that need review</a></p>
@endsection
