<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Removes stored image files after an account has been deleted. It carries the paths itself (the rows that held
 * them are gone), and is retried with backoff, so a storage outage delays cleanup instead of losing it. A job that
 * exhausts its retries stays in failed_jobs with the full path list for manual cleanup.
 */
class DeleteStoredFiles implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @param  array<string, array<int, string>>  $files  disk name => paths */
    public function __construct(public array $files)
    {
        // Only run once the database transaction that removed the rows has committed.
        $this->afterCommit();
    }

    /** @return array<int, int> seconds between attempts */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(): void
    {
        foreach ($this->files as $disk => $paths) {
            // Deleting a path that no longer exists is not an error, so a retry after a partial run is safe.
            Storage::disk($disk)->delete($paths);
        }
    }
}
