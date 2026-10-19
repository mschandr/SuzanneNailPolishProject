<?php

namespace App\Http\Controllers;

use App\Models\Manicure;
use App\Support\PhotoProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManicureController extends Controller
{
    public function index(Request $request)
    {
        return view('manicures.index', [
            'manicures' => $request->user()->manicures()->with('photos', 'user')
                ->orderByDesc('worn_on')->orderByDesc('created_at')->paginate(24),
        ]);
    }

    public function create()
    {
        return view('manicures.form', ['manicure' => new Manicure(['worn_on' => now()])]);
    }

    public function store(Request $request, PhotoProcessor $processor)
    {
        $data = $request->validate(
            $this->rules($request, null) + ['photos' => $this->photosRule($request, 0, required: true)],
            ['photos.max' => 'You can have at most '.Manicure::MAX_PHOTOS.' photos on a manicure.'],
        );

        $manicure = new Manicure($this->attributes($data));
        $manicure->user_id = $request->user()->id;

        $this->persist($manicure, $request, $processor);

        return redirect("/manicures/{$manicure->id}")->with('status', 'Manicure saved.');
    }

    public function show(Request $request, string $id)
    {
        $manicure = Manicure::with(['photos', 'user', 'layers.polish.brand'])->findOrFail($id);

        // A private Manicure answers exactly like one that does not exist.
        abort_unless($manicure->visibleTo($request->user()), 404);

        return view('manicures.show', [
            'manicure' => $manicure,
            'isOwner' => $request->user()?->id === $manicure->user_id,
        ]);
    }

    public function edit(Request $request, string $id)
    {
        return view('manicures.form', ['manicure' => $this->mine($request, $id)->load('photos')]);
    }

    public function update(Request $request, string $id, PhotoProcessor $processor)
    {
        $manicure = $this->mine($request, $id);

        $existing = $manicure->photos()->count();
        $room = max(0, Manicure::MAX_PHOTOS - $existing);

        $data = $request->validate(
            $this->rules($request, $manicure) + ['photos' => $this->photosRule($request, $existing, required: false)],
            ['photos.max' => $room === 0
                ? 'This manicure already has '.Manicure::MAX_PHOTOS.' photos. Remove one to add another.'
                : "This manicure has {$existing} ".Str::plural('photo', $existing).", so you can add {$room} more."],
        );

        $manicure->fill($this->attributes($data));

        $this->persist($manicure, $request, $processor);

        return redirect("/manicures/{$manicure->id}")->with('status', 'Manicure updated.');
    }

    public function destroy(Request $request, string $id)
    {
        $manicure = $this->mine($request, $id)->load('photos');
        $files = $manicure->photos->flatMap(fn ($p) => [$p->path, $p->thumb_path])->all();

        $manicure->delete(); // photo rows go with it via the foreign key

        $this->deleteFiles($files);

        return redirect('/manicures')->with('status', 'Manicure deleted.');
    }

    /** The Manicure is already gone; a storage hiccup must not turn that into an error page. */
    private function deleteFiles(array $files): void
    {
        try {
            Storage::disk(config('nailpolish.photos_disk'))->delete($files);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function mine(Request $request, string $id): Manicure
    {
        return $request->user()->manicures()->findOrFail($id);
    }

    /** @return array<string, mixed> */
    private function rules(Request $request, ?Manicure $manicure): array
    {
        return [
            'worn_on' => ['required', 'date', 'before_or_equal:tomorrow'],
            'caption' => ['nullable', 'string', 'max:500'],
            'visibility' => ['nullable', 'in:'.Manicure::PUBLIC.','.Manicure::PRIVATE],
            'photos.*' => $this->photoFileRules(),
        ];
    }

    /** @return array<string, mixed> */
    private function attributes(array $data): array
    {
        return [
            'worn_on' => $data['worn_on'],
            'caption' => filled($data['caption'] ?? null) ? trim($data['caption']) : null,
            'visibility' => filled($data['visibility'] ?? null) ? $data['visibility'] : null,
        ];
    }

    /** At most 4 photos per Manicure and the per-User cap overall. */
    private function photosRule(Request $request, int $existing, bool $required): array
    {
        $room = max(0, Manicure::MAX_PHOTOS - $existing);
        $userRoom = max(0, config('nailpolish.max_photos_per_user') - $request->user()->photoCount());

        return [
            $required ? 'required' : 'nullable', 'array',
            $required ? 'min:1' : 'min:0',
            'max:'.$room,
            function ($attribute, $files, $fail) use ($userRoom) {
                if (is_array($files) && count($files) > $userRoom) {
                    $fail("You've reached the limit of ".config('nailpolish.max_photos_per_user').' photos. Delete some to make room.');
                }
            },
        ];
    }

    /** @return array<int, mixed> */
    private function photoFileRules(): array
    {
        return [
            'file',
            'mimes:jpg,jpeg,png,webp',
            'max:10240', // kilobytes
            function ($attribute, $file, $fail) {
                $dimensions = PhotoProcessor::dimensions($file->getRealPath());

                if ($dimensions === null) {
                    $fail('That file is not a readable JPG, PNG or WebP image.');
                } elseif ($dimensions[0] * $dimensions[1] > PhotoProcessor::MAX_PIXELS) {
                    $fail('That image has too many pixels (the limit is 40 megapixels). Please use a smaller photo.');
                }
            },
        ];
    }

    /** Save the Manicure and any newly uploaded photos; on failure remove files already written. */
    private function persist(Manicure $manicure, Request $request, PhotoProcessor $processor): void
    {
        // Process everything before touching the database, so a bad image leaves nothing behind.
        try {
            $processed = collect($request->file('photos', []))
                ->map(fn ($file) => $processor->process($file->getRealPath()));
        } catch (\RuntimeException) {
            // Passed the header checks but GD cannot decode it (truncated or corrupt file).
            throw ValidationException::withMessages(['photos' => 'One of those images is damaged and could not be read. Please try another copy.']);
        }

        $disk = Storage::disk(config('nailpolish.photos_disk'));
        $written = [];

        try {
            DB::transaction(function () use ($manicure, $processed, $disk, &$written) {
                $manicure->save();

                $next = $manicure->photos()->max('position');
                $next = $next === null ? 0 : $next + 1;

                foreach ($processed as $images) {
                    $base = 'manicures/'.Str::uuid();
                    $disk->put($path = "{$base}.jpg", $images['full']);
                    $written[] = $path;
                    $disk->put($thumb = "{$base}-thumb.jpg", $images['thumb']);
                    $written[] = $thumb;

                    $manicure->photos()->create(['position' => $next++, 'path' => $path, 'thumb_path' => $thumb]);
                }
            });
        } catch (\Throwable $e) {
            $disk->delete($written); // don't leave orphaned files if the save failed

            throw $e;
        }
    }
}
