<?php

namespace Database\Seeders;

use App\Support\PhotoProcessor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A realistic volume of data for load testing. It REFUSES to run against the normal `public` schema: point it at an
 * isolated one, e.g.
 *
 *   DB_SEARCH_PATH=loadtest php artisan migrate --force
 *   DB_SEARCH_PATH=loadtest php artisan db:seed --class=LoadTestSeeder --force
 *
 * (It reads its settings with getenv(), not env(), so they work even when Laravel's config cache is active.)
 * Size it with LT_USERS, LT_BRANDS, LT_POLISHES_PER_BRAND, LT_MANICURES_PER_USER. Run again later to mint fresh sign-in
 * tokens only (they are single-use); set LT_FRESH=1 to wipe and rebuild. The sign-in tokens are written to
 * storage/app/loadtest-users.json for the k6 script.
 */
class LoadTestSeeder extends Seeder
{
    public function run(): void
    {
        $schema = config('database.connections.pgsql.search_path');
        if (config('database.default') !== 'pgsql' || $schema === 'public' || blank($schema)) {
            throw new \RuntimeException('Refusing to seed load-test data into the real database. Set DB_SEARCH_PATH to an isolated schema (e.g. loadtest).');
        }

        if (getenv('LT_FRESH')) {
            foreach (['hearts', 'manicure_polishes', 'manicure_photos', 'manicures', 'bottles', 'locations', 'finish_polish', 'polishes', 'brands', 'magic_links', 'sessions', 'users'] as $table) {
                DB::table($table)->delete();
            }
        }

        if (DB::table('users')->count() === 0) {
            $this->build();
        }

        $this->mintTokens();
    }

    private function build(): void
    {
        $nUsers = (int) (getenv('LT_USERS') ?: 300);
        $nBrands = (int) (getenv('LT_BRANDS') ?: 150);
        $perBrand = (int) (getenv('LT_POLISHES_PER_BRAND') ?: 40);
        $perUser = (int) (getenv('LT_MANICURES_PER_USER') ?: 8);
        $id = fn () => (string) Str::orderedUuid();
        $now = now();

        $this->call(DatabaseSeeder::class); // finishes and colour families
        $finishIds = DB::table('finishes')->pluck('id')->all();
        $colorIds = DB::table('color_families')->pluck('id')->all();

        // --- photos: a few real files shared by many rows (the bytes are what matter for serving) ---
        $files = $this->photoFiles();

        // --- users ---
        $users = [];
        for ($i = 1; $i <= $nUsers; $i++) {
            $users[] = [
                'id' => $id(), 'display_name' => "User {$i}", 'email' => "load{$i}@example.test", 'email_verified_at' => $now,
                'signup_method' => 'invite', 'onboarding_completed_at' => $now, 'is_admin' => false,
                'manicure_default_visibility' => 'public', 'created_at' => $now, 'updated_at' => $now,
            ];
        }
        $this->insert('users', $users);
        $userIds = array_column($users, 'id');

        // --- catalog ---
        $brands = $polishes = $pivot = [];
        for ($b = 1; $b <= $nBrands; $b++) {
            $brandId = $id();
            $brands[] = ['id' => $brandId, 'name' => "Brand {$b}", 'name_key' => "brand {$b}", 'slug' => "brand-{$b}", 'created_at' => $now, 'updated_at' => $now];
            for ($p = 1; $p <= $perBrand; $p++) {
                $pid = $id();
                $polishes[] = [
                    'id' => $pid, 'brand_id' => $brandId, 'name' => "Shade {$b}-{$p}", 'name_key' => "shade {$b}-{$p}",
                    'color_family_id' => $colorIds[array_rand($colorIds)], 'created_at' => $now, 'updated_at' => $now,
                ];
                $pivot[] = ['polish_id' => $pid, 'finish_id' => $finishIds[array_rand($finishIds)]];
            }
        }
        $this->insert('brands', $brands);
        $this->insert('polishes', $polishes);
        $this->insert('finish_polish', $pivot);
        $polishIds = array_column($polishes, 'id');

        // --- each user's collection ---
        $locations = $bottles = [];
        foreach ($userIds as $uid) {
            $locIds = [];
            foreach (['Cabinet', 'Drawer', 'Under the bed', 'Travel case', 'Desk'] as $name) {
                $lid = $id();
                $locIds[] = $lid;
                $locations[] = ['id' => $lid, 'user_id' => $uid, 'name' => $name, 'name_key' => Str::lower($name), 'created_at' => $now, 'updated_at' => $now];
            }
            foreach (array_rand(array_flip($polishIds), 40) as $pid) {
                $bottles[] = ['id' => $id(), 'user_id' => $uid, 'polish_id' => $pid, 'location_id' => $locIds[array_rand($locIds)], 'status' => 'owned', 'created_at' => $now, 'updated_at' => $now];
            }
        }
        $this->insert('locations', $locations);
        $this->insert('bottles', $bottles);

        // --- manicures with photos and polish labels ---
        $manicures = $photos = $layers = $manicureIds = [];
        foreach ($userIds as $uid) {
            for ($m = 0; $m < $perUser; $m++) {
                $mid = $id();
                $manicureIds[] = $mid;
                $manicures[] = [
                    'id' => $mid, 'user_id' => $uid, 'worn_on' => now()->subDays(random_int(0, 365))->toDateString(),
                    'caption' => 'Load test manicure', 'visibility' => random_int(1, 10) === 1 ? 'private' : 'public',
                    'polish_mode' => 'hand', 'created_at' => $now, 'updated_at' => $now,
                ];
                foreach (range(0, random_int(0, 2)) as $pos) {
                    $f = $files[array_rand($files)];
                    $photos[] = ['id' => $id(), 'manicure_id' => $mid, 'position' => $pos, 'path' => $f['path'], 'thumb_path' => $f['thumb'], 'created_at' => $now, 'updated_at' => $now];
                }
                foreach (range(0, random_int(1, 3)) as $pos) {
                    $layers[] = ['id' => $id(), 'manicure_id' => $mid, 'polish_id' => $polishIds[array_rand($polishIds)], 'target' => 'hand', 'position' => $pos, 'created_at' => $now, 'updated_at' => $now];
                }
            }
        }
        $this->insert('manicures', $manicures);
        $this->insert('manicure_photos', $photos);
        $this->insert('manicure_polishes', $layers);

        // --- hearts ---
        $hearts = [];
        foreach ($userIds as $uid) {
            foreach (array_rand(array_flip($manicureIds), min(15, count($manicureIds))) as $mid) {
                $hearts[$uid.$mid] = ['user_id' => $uid, 'manicure_id' => $mid, 'created_at' => $now];
            }
        }
        $this->insert('hearts', array_values($hearts));

        $this->command?->info(sprintf('Seeded %d users, %d polishes, %d bottles, %d manicures, %d photos, %d hearts.',
            count($users), count($polishes), count($bottles), count($manicures), count($photos), count($hearts)));
    }

    /** Fresh single-use sign-in tokens for every seeded user, written for the k6 script. */
    private function mintTokens(): void
    {
        DB::table('magic_links')->delete();
        $now = now();
        $rows = $out = [];

        foreach (DB::table('users')->orderBy('email')->get(['id', 'email']) as $user) {
            $token = Str::random(48);
            $rows[] = ['id' => (string) Str::orderedUuid(), 'email' => $user->email, 'token_hash' => hash('sha256', $token),
                'expires_at' => $now->copy()->addDay(), 'created_at' => $now, 'updated_at' => $now];
            $out[] = ['email' => $user->email, 'token' => $token];
        }
        $this->insert('magic_links', $rows);

        File::put(storage_path('app/loadtest-users.json'), json_encode($out));
        $this->command?->info(count($out).' sign-in tokens written to storage/app/loadtest-users.json');
    }

    /** @return array<int, array{path: string, thumb: string}> */
    private function photoFiles(): array
    {
        $disk = Storage::disk(config('nailpolish.photos_disk'));
        $processor = new PhotoProcessor;
        $sources = glob(__DIR__.'/samples/*.jpg');

        // Four synthetic 1600x1200 pictures with noise, so the JPEGs are a realistic size rather than flat colour.
        $dir = sys_get_temp_dir().'/lt-'.getmypid();
        @mkdir($dir);
        for ($i = 1; $i <= 4; $i++) {
            $im = imagecreatetruecolor(2400, 1800);
            for ($y = 0; $y < 1800; $y += 6) {
                imagefilledrectangle($im, 0, $y, 2400, $y + 6, imagecolorallocate($im, (int) ($y / 7 + $i * 40) % 255, (int) (255 - $y / 8 + $i * 20) % 255, (int) ($i * 70 + $y / 9) % 255));
            }
            for ($n = 0; $n < 4000; $n++) {
                imagesetpixel($im, random_int(0, 2399), random_int(0, 1799), imagecolorallocate($im, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
            }
            imagejpeg($im, "{$dir}/synthetic-{$i}.jpg", 90);
            $sources[] = "{$dir}/synthetic-{$i}.jpg";
        }

        $files = [];
        foreach ($sources as $n => $source) {
            $images = $processor->process($source);
            $disk->put($path = "loadtest/{$n}.jpg", $images['full']);
            $disk->put($thumb = "loadtest/{$n}-thumb.jpg", $images['thumb']);
            $files[] = ['path' => $path, 'thumb' => $thumb];
        }
        File::deleteDirectory($dir);

        return $files;
    }

    /** @param  array<int, array<string, mixed>>  $rows */
    private function insert(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }
}
