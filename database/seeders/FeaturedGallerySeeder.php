<?php

namespace Database\Seeders;

use App\Models\Manicure;
use App\Models\User;
use App\Support\PhotoProcessor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Seeds the Gallery with a few openly licensed photos (see database/seeders/samples/ATTRIBUTION.md), shown
 * under a "Featured" account with full credit. Safe to run repeatedly, in any environment.
 */
class FeaturedGallerySeeder extends Seeder
{
    private const PHOTOS = [
        [
            'file' => 'blue-cat-eye-nail-art.jpg',
            'caption' => 'Purple cat-eye polish with star decals.',
            'credit' => 'Photo "Blue cat eye nail art" by Liekkikoira, CC BY-SA 4.0, via Wikimedia Commons. Resized.',
            'credit_url' => 'https://commons.wikimedia.org/wiki/File:Blue_cat_eye_nail_art.jpg',
            'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0',
        ],
        [
            'file' => 'hologram-nail-art.jpg',
            'caption' => 'Holographic polish with black star stamping.',
            'credit' => 'Photo "Hologram nail art" by Courtney Rhodes, CC BY 2.0, via Wikimedia Commons. Resized.',
            'credit_url' => 'https://commons.wikimedia.org/wiki/File:Hologram_nail_art.jpg',
            'license_url' => 'https://creativecommons.org/licenses/by/2.0',
        ],
    ];

    public function run(PhotoProcessor $processor): void
    {
        $featured = User::firstOrNew(['email' => 'featured@gallery.invalid']);

        if (! $featured->exists) {
            $featured->display_name = 'Featured';
            $featured->email_verified_at = now();
            // Not a self-signup (so it never uses up one of the 100 free places), and nobody can sign in as it:
            // the address is not deliverable.
            $featured->forceFill(['signup_method' => 'system', 'onboarding_completed_at' => now()])->save();
        }

        $disk = Storage::disk(config('nailpolish.photos_disk'));

        foreach (self::PHOTOS as $photo) {
            if (Manicure::where('user_id', $featured->id)->where('credit_url', $photo['credit_url'])->exists()) {
                continue;
            }

            $images = $processor->process(__DIR__.'/samples/'.$photo['file']);
            $base = 'manicures/'.Str::uuid();
            $disk->put($path = "{$base}.jpg", $images['full']);
            $disk->put($thumb = "{$base}-thumb.jpg", $images['thumb']);

            $manicure = new Manicure(['worn_on' => now()->toDateString(), 'caption' => $photo['caption'], 'visibility' => Manicure::PUBLIC]);
            $manicure->user_id = $featured->id;
            $manicure->forceFill([
                'credit' => $photo['credit'],
                'credit_url' => $photo['credit_url'],
                'license_url' => $photo['license_url'],
            ])->save();

            $manicure->photos()->create(['position' => 0, 'path' => $path, 'thumb_path' => $thumb]);
        }
    }
}
