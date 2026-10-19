<?php

namespace App\Http\Controllers;

use App\Models\ManicurePhoto;
use App\Support\PhotoProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ManicurePhotoController extends Controller
{
    /**
     * Photos are never public files: every request is checked against the Manicure's current visibility,
     * so making a Manicure private takes effect at once.
     */
    public function show(Request $request, string $id, string $variant)
    {
        abort_unless(in_array($variant, ['full', 'thumb'], true), 404);

        $photo = ManicurePhoto::with('manicure.user')->findOrFail($id);
        $manicure = $photo->manicure;

        abort_unless($manicure->visibleTo($request->user()), 404);

        // Public photos are cached for a minute at most, so a hidden or private photo stops being served within a minute\n        // even from shared caches. Private ones must never sit in any cache.
        $cache = $manicure->isPublic() ? 'public, max-age=60' : 'private, no-store';

        return $this->disk()->response($variant === 'thumb' ? $photo->thumb_path : $photo->path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => $cache,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function rotate(Request $request, string $id, PhotoProcessor $processor)
    {
        $photo = $this->mine($request, $id);
        $data = $request->validate(['direction' => ['required', 'in:left,right']]);

        $images = $processor->rotate($this->disk()->get($photo->path), $data['direction'] === 'right' ? 90 : -90);

        // Same paths, new contents; touching the row changes the ?v= the pages append, busting browser caches.
        $this->disk()->put($photo->path, $images['full']);
        $this->disk()->put($photo->thumb_path, $images['thumb']);
        $photo->touch();

        return redirect("/manicures/{$photo->manicure_id}/edit");
    }

    public function destroy(Request $request, string $id)
    {
        $photo = $this->mine($request, $id);

        if ($photo->manicure->photos()->count() <= 1) {
            throw ValidationException::withMessages(['photo' => 'A manicure needs at least one photo. Delete the whole manicure instead.']);
        }

        $files = [$photo->path, $photo->thumb_path];
        $manicureId = $photo->manicure_id;
        $photo->delete();

        try {
            $this->disk()->delete($files);
        } catch (\Throwable $e) {
            report($e); // the photo is already gone; don't turn that into an error page
        }

        return redirect("/manicures/{$manicureId}/edit");
    }

    private function mine(Request $request, string $id): ManicurePhoto
    {
        $photo = ManicurePhoto::whereHas('manicure', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with('manicure')->findOrFail($id);

        $photo->manicure->abortIfLockedForEditing();

        return $photo;
    }

    private function disk()
    {
        return Storage::disk(config('nailpolish.photos_disk'));
    }
}
