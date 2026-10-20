<?php

namespace Tests\Support;

use App\Models\Brand;
use App\Models\ColorFamily;
use App\Models\Finish;
use App\Models\Manicure;
use App\Models\ManicurePhoto;
use App\Models\ManicurePolish;
use App\Models\Polish;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Builds Manicures, Polishes and hearts directly, without going through uploads. */
trait BuildsGalleryData
{
    protected function person(string $name): User
    {
        return User::factory()->create(['display_name' => $name]);
    }

    protected function brand(string $name): Brand
    {
        return Brand::firstOrCreate(['name_key' => mb_strtolower($name)], ['name' => $name]);
    }

    protected function polish(string $brand, string $name, ?string $color = null, array $finishes = [], ?User $private = null): Polish
    {
        $polish = new Polish(['brand_id' => $this->brand($brand)->id, 'name' => $name]);
        if ($color) {
            $polish->color_family_id = ColorFamily::firstOrCreate(['name_key' => mb_strtolower($color)], ['name' => $color])->id;
        }
        $polish->user_id = $private?->id;
        $polish->save();
        foreach ($finishes as $finish) {
            $polish->finishes()->attach(Finish::firstOrCreate(['name_key' => mb_strtolower($finish)], ['name' => $finish])->id);
        }

        return $polish;
    }

    /** @param  array<int, Polish>  $polishes */
    protected function manicure(User $owner, array $polishes = [], ?string $visibility = null, string $date = '2026-10-15', string $caption = ''): Manicure
    {
        $m = new Manicure(['worn_on' => $date, 'caption' => $caption ?: null, 'visibility' => $visibility]);
        $m->user_id = $owner->id;
        $m->save();

        $photo = new ManicurePhoto(['position' => 0, 'path' => "manicures/{$m->id}.jpg", 'thumb_path' => "manicures/{$m->id}-thumb.jpg"]);
        $photo->manicure_id = $m->id;
        $photo->save();

        foreach (array_values($polishes) as $i => $polish) {
            $layer = new ManicurePolish;
            $layer->manicure_id = $m->id;
            $layer->polish_id = $polish->id;
            $layer->position = $i;
            $layer->save();
        }

        return $m;
    }

    protected function heart(User $by, Manicure $m): void
    {
        DB::table('hearts')->insert(['user_id' => $by->id, 'manicure_id' => $m->id]);
    }

    protected function link(Manicure $m): string
    {
        return "/manicures/{$m->id}";
    }
}
