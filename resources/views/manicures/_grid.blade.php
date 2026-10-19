<ul style="list-style:none;padding:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(9rem,1fr));gap:1rem">
    @forelse ($manicures as $manicure)
        @php($cover = $manicure->photos->first())
        <li>
            <a href="/manicures/{{ $manicure->id }}">
                @if ($cover)<img src="/photos/{{ $cover->id }}/thumb?v={{ $cover->updated_at->timestamp }}" alt="Manicure from {{ $manicure->worn_on->toFormattedDateString() }}" loading="lazy" style="width:100%;aspect-ratio:1;object-fit:cover">@endif
                <div>{{ $manicure->worn_on->toFormattedDateString() }}</div>
            </a>
            @if (! empty($showPrivacy))<small>{{ $manicure->hidden_at ? 'Hidden by a moderator' : ($manicure->isPublic() ? 'Public' : 'Only you') }}</small>@endif
        </li>
    @empty
        <li>No manicures yet.</li>
    @endforelse
</ul>
