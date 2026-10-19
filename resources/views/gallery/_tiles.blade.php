<ul style="list-style:none;padding:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(9rem,1fr));gap:1rem">
    @forelse ($manicures as $manicure)
        @php($cover = $manicure->photos->first())
        <li>
            <a href="/manicures/{{ $manicure->id }}">
                @if ($cover)
                    <img src="/photos/{{ $cover->id }}/thumb?v={{ $cover->updated_at->timestamp }}" alt="Manicure by {{ $manicure->user->display_name }}" loading="lazy" style="width:100%;aspect-ratio:1;object-fit:cover">
                @else
                    <span style="display:flex;align-items:center;justify-content:center;aspect-ratio:1;background:#eee">No photo</span>
                @endif
            </a>
            <div>{{ $manicure->user->display_name }}
                @if ($manicure->hearts_count > 0)<small>· {{ $manicure->hearts_count }} {{ Str::plural('heart', $manicure->hearts_count) }}</small>@endif</div>
        </li>
    @empty
        <li>No manicures found.</li>
    @endforelse
</ul>
