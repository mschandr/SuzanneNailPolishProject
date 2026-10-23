@if ($manicures->isEmpty())
    <div class="empty">
        <p>No manicures found.</p>
        @auth<a class="btn btn-primary" href="/manicures/create">Upload your first manicure</a>@endauth
    </div>
@else
    <ul class="tiles">
        @foreach ($manicures as $manicure)
            @php($cover = $manicure->photos->first())
            <li class="tile">
                <a href="/manicures/{{ $manicure->id }}">
                    @if ($cover)
                        <img src="/photos/{{ $cover->id }}/thumb?v={{ $cover->updated_at->timestamp }}" alt="Manicure by {{ $manicure->user->display_name }}" width="400" height="400" loading="lazy">
                    @else
                        <span class="noimg">No photo</span>
                    @endif
                    <span class="caption">{{ $manicure->user->display_name }}
                        @if ($manicure->hearts_count > 0)<small>♥ {{ $manicure->hearts_count }} {{ Str::plural('heart', $manicure->hearts_count) }}</small>@endif</span>
                </a>
            </li>
        @endforeach
    </ul>
@endif
