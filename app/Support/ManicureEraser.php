<?php

namespace App\Support;

use App\Models\Manicure;
use Illuminate\Support\Facades\Storage;

class ManicureEraser
{
    /** Delete a Manicure (its photos, labels, hearts and reports go with it by foreign key) and its files. */
    public static function erase(Manicure $manicure): void
    {
        self::deleteFiles(self::eraseRecord($manicure));
    }

    /**
     * Delete only the database rows and return the file paths to remove. Callers that must keep the deletion
     * atomic with something else (an audit entry) do this inside a transaction, then call deleteFiles() after it commits.
     *
     * @return array<int, string>
     */
    public static function eraseRecord(Manicure $manicure): array
    {
        $manicure->loadMissing('photos');
        $files = $manicure->photos->flatMap(fn ($p) => [$p->path, $p->thumb_path])->all();

        $manicure->delete();

        return $files;
    }

    /** The rows are already gone; a storage hiccup must not turn that into an error page. */
    public static function deleteFiles(array $files): void
    {
        try {
            Storage::disk(config('nailpolish.photos_disk'))->delete($files);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
