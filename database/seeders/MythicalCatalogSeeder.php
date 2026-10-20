<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\ColorFamily;
use App\Models\Finish;
use App\Models\Polish;
use App\Support\CatalogCache;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Made-up brands and shades for development and demos. Not part of DatabaseSeeder,
 * so it never runs in production. Safe to run repeatedly.
 */
class MythicalCatalogSeeder extends Seeder
{
    // brand => [shade, color family, finishes, hex swatch, collection, vendor link]
    private const CATALOG = [
        'Phoenix Parlour' => [
            ['Ember Rising', 'Red', ['Metallic'], '#c1272d', 'Bestiary Vol. 1', 'https://example.com/phoenix-parlour/ember-rising'],
            ['Ashes to Ashes', 'Gray', ['Matte'], '#8d8d92', 'Bestiary Vol. 1', null],
            ['Golden Plume', 'Yellow', ['Shimmer'], '#f2b632', 'Bestiary Vol. 1', null],
            ['Rebirth', 'Orange', ['Duochrome', 'Shimmer'], '#ec7a2c', null, 'https://example.com/phoenix-parlour/rebirth'],
        ],
        'Moonlit Menagerie' => [
            ['Unicorn Hoof', 'White', ['Holographic'], '#f4f1fa', 'Gentle Beasts', null],
            ['Selkie Sigh', 'Blue', ['Jelly'], '#3f7fbf', 'Gentle Beasts', 'https://example.com/moonlit-menagerie/selkie-sigh'],
            ['Kitsune Tail', 'Orange', ['Glitter'], '#d9772b', null, null],
        ],
        'Basilisk & Bramble' => [
            ['Mandrake Scream', 'Purple', ['Creme'], '#6b3fa0', null, null],
            ["Kraken's Ink", 'Black', ['Shimmer', 'Sheer'], '#15151f', 'Deep Things', null],
            ["Gorgon's Gaze", 'Green', ['Magnetic'], '#2f8f5b', 'Deep Things', null],
        ],
    ];

    public function run(): void
    {
        $this->call(DatabaseSeeder::class); // the Finish and Color family lists these shades use

        foreach (self::CATALOG as $brandName => $shades) {
            $brand = Brand::firstOrCreate(['name_key' => Str::lower($brandName)], ['name' => $brandName]);

            foreach ($shades as [$name, $color, $finishes, $hex, $collection, $url]) {
                if (Polish::where('brand_id', $brand->id)->where('name_key', Str::lower($name))->exists()) {
                    continue;
                }

                $path = 'swatches/mythical-'.Str::slug($brandName.' '.$name).'.svg';
                Storage::disk('public')->put($path, $this->swatch($hex));

                $polish = Polish::create([
                    'brand_id' => $brand->id,
                    'name' => $name,
                    'collection' => $collection,
                    'color_family_id' => ColorFamily::where('name_key', Str::lower($color))->value('id'),
                    'vendor_url' => $url,
                    'swatch_path' => $path,
                ]);

                $polish->finishes()->sync(
                    Finish::whereIn('name_key', array_map(Str::lower(...), $finishes))->pluck('id'),
                );
            }
        }

        CatalogCache::bump();
    }

    private function swatch(string $hex): string
    {
        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120">
          <rect width="120" height="120" fill="#f6f1f4"/>
          <rect x="44" y="6" width="32" height="26" rx="4" fill="#2b2b33"/>
          <rect x="30" y="32" width="60" height="82" rx="14" fill="{$hex}"/>
          <rect x="38" y="42" width="10" height="56" rx="5" fill="#ffffff" opacity="0.28"/>
        </svg>
        SVG;
    }
}
