@if ($manicures->isEmpty())
    <div class="empty">
        <p>No manicures yet.</p>
        @if (! empty($showPrivacy))<a class="btn btn-primary" href="/manicures/create">Upload your first manicure</a>@endif
    </div>
@else
    <ul class="tiles">
        @foreach ($manicures as $manicure)
            @php($cover = $manicure->photos->first())
            <li class="tile">
                <a href="/manicures/{{ $manicure->id }}">
                    @if ($cover)<img src="/photos/{{ $cover->id }}/thumb?v={{ $cover->updated_at->timestamp }}" alt="Manicure from {{ $manicure->worn_on->toFormattedDateString() }}" width="400" height="400" loading="lazy">@else<span class="noimg">No photo</span>@endif
                    <span class="caption">{{ $manicure->worn_on->toFormattedDateString() }}
                        @if (! empty($showPrivacy))<small>{{ $manicure->hidden_at ? 'Hidden by a moderator' : ($manicure->isPublic() ? 'Public' : 'Only you') }}</small>@endif</span>
                </a>
            </li>
        @endforeach
    </ul>
@endif
