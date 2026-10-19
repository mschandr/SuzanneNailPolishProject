<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\ColorFamily;
use App\Models\Finish;
use App\Models\Import;
use App\Models\Polish;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Reads an uploaded CSV of polishes into rows ready to preview, each with a status:
 *   new        not in the Catalog yet and valid: can be added
 *   exists     the Catalog already has that brand + shade (public ones only): never touched
 *   duplicate  the same brand + shade appeared earlier in this file
 *   invalid    something is wrong with the row (see its problems)
 * Colour family and finish names that don't match the Admin-managed lists are a warning, not an error: the polish is
 * added without them and flagged for review.
 */
class PolishCsv
{
    public const COLUMNS = ['brand', 'shade', 'collection', 'color_family', 'finishes', 'vendor_url', 'image_url'];

    public const TEMPLATE = "brand,shade,collection,color_family,finishes,vendor_url,image_url\n"
        ."Phoenix Parlour,Ember Rising,Bestiary Vol. 1,Red,Metallic,https://example.com/phoenix-parlour/ember-rising,\n"
        .'Moonlit Menagerie,Unicorn Hoof,Gentle Beasts,White,Holographic;Shimmer,,'."\n";

    /** @return array<int, array<string, mixed>> rows: line, the seven fields, status, problems */
    public function parse(string $content): array
    {
        $content = $this->clean($content);
        $records = $this->records($content);

        if ($records === []) {
            $this->fail('That file is empty.');
        }

        [, $header] = array_shift($records);
        $columns = array_map(fn ($h) => Str::of((string) $h)->trim()->lower()->replace([' ', '-'], '_')->toString(), $header);

        foreach (self::COLUMNS as $known) {
            if (count(array_keys($columns, $known, true)) > 1) {
                $this->fail("The column \"{$known}\" appears more than once. Each column name may be used once.");
            }
        }

        foreach (['brand', 'shade'] as $required) {
            if (! in_array($required, $columns, true)) {
                $this->fail("The file needs a \"{$required}\" column. The first row must be the column names.");
            }
        }

        $rows = [];
        foreach ($records as [$line, $fields]) {
            $row = ['line' => $line];
            foreach (self::COLUMNS as $name) {
                $index = array_search($name, $columns, true);
                $row[$name] = $index === false ? null : $this->value($fields[$index] ?? null);
            }

            // A row with nothing in any known column is blank, whatever else is in the line.
            if (collect(self::COLUMNS)->every(fn ($c) => $row[$c] === null)) {
                continue;
            }
            $rows[] = $row;
        }

        if ($rows === []) {
            $this->fail('The file has no rows below the column names.');
        }

        if (count($rows) > Import::MAX_ROWS) {
            $this->fail('That file has '.count($rows).' rows. The limit is '.Import::MAX_ROWS.' per file: split it into smaller files.');
        }

        return $this->classify($rows);
    }

    /** @param  array<int, array<string, mixed>>  $rows */
    private function classify(array $rows): array
    {
        $existing = $this->existingPublicKeys($rows);
        $colors = ColorFamily::pluck('name_key')->flip();
        $finishes = Finish::pluck('name_key')->flip();
        $seen = [];

        foreach ($rows as &$row) {
            $problems = [];
            $brand = $row['brand'];
            $shade = $row['shade'];

            if ($brand === null) {
                $problems[] = 'Brand is required.';
            } elseif (mb_strlen($brand) > 100) {
                $problems[] = 'Brand name is longer than 100 characters.';
            }
            if ($shade === null) {
                $problems[] = 'Shade name is required.';
            } elseif (mb_strlen($shade) > 150) {
                $problems[] = 'Shade name is longer than 150 characters.';
            }
            if ($row['collection'] !== null && mb_strlen($row['collection']) > 150) {
                $problems[] = 'Collection is longer than 150 characters.';
            }
            if ($row['vendor_url'] !== null && ! $this->isUrl($row['vendor_url'], ['http', 'https'])) {
                $problems[] = 'Vendor link must be a full address starting with http:// or https://.';
            }
            if ($row['image_url'] !== null && ! $this->isUrl($row['image_url'], ['https'])) {
                $problems[] = 'Image link must be a full address starting with https://.';
            }

            if ($problems !== []) {
                $row['status'] = 'invalid';
                $row['problems'] = implode(' ', $problems);

                continue;
            }

            $key = Str::lower($brand).'|'.Str::lower($shade);

            if (isset($existing[$key])) {
                $row['status'] = 'exists';
                $row['problems'] = 'Already in the Catalog: left as it is.';
            } elseif (isset($seen[$key])) {
                $row['status'] = 'duplicate';
                $row['problems'] = "Appears earlier in this file (line {$seen[$key]}).";
            } else {
                $seen[$key] = $row['line'];
                $row['status'] = 'new';
                $row['problems'] = $this->warnings($row, $colors, $finishes);
            }
        }

        return $rows;
    }

    /** What will be left blank because it doesn't match the Admin-managed lists. */
    private function warnings(array $row, $colors, $finishes): ?string
    {
        $notes = [];

        if ($row['color_family'] !== null && ! isset($colors[Str::lower($row['color_family'])])) {
            $notes[] = "Colour family \"{$row['color_family']}\" is not in the list, so it is left blank.";
        }
        foreach (self::splitFinishes($row['finishes']) as $name) {
            if (! isset($finishes[Str::lower($name)])) {
                $notes[] = "Finish \"{$name}\" is not in the list, so it is left out.";
            }
        }

        return $notes === [] ? null : implode(' ', $notes);
    }

    /** @return array<int, string> */
    public static function splitFinishes(?string $finishes): array
    {
        return collect(preg_split('/[;|]/', (string) $finishes))->map(fn ($f) => trim($f))->filter()->unique(fn ($f) => Str::lower($f))->values()->all();
    }

    /** "brand key|shade key" for every public Polish that matches a brand in the file. */
    private function existingPublicKeys(array $rows): array
    {
        $brandKeys = collect($rows)->pluck('brand')->filter()->map(fn ($b) => Str::lower($b))->unique()->values();

        if ($brandKeys->isEmpty()) {
            return [];
        }

        return Polish::public()
            ->join('brands', 'brands.id', '=', 'polishes.brand_id')
            ->whereIn('brands.name_key', $brandKeys)
            ->get(['brands.name_key as brand_key', 'polishes.name_key as shade_key'])
            ->mapWithKeys(fn ($p) => [$p->brand_key.'|'.$p->shade_key => true])
            ->all();
    }

    private function isUrl(string $value, array $schemes): bool
    {
        return mb_strlen($value) <= 2048
            && filter_var($value, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), $schemes, true);
    }

    private function value(?string $raw): ?string
    {
        $value = trim((string) $raw);

        return $value === '' ? null : $value;
    }

    private function clean(string $content): string
    {
        if (str_contains($content, "\0")) {
            $this->fail('That does not look like a CSV text file.');
        }
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }
        if (! mb_check_encoding($content, 'UTF-8')) {
            $this->fail('That file is not UTF-8 text. In your spreadsheet, choose "Save as CSV UTF-8".');
        }

        return $content;
    }

    /**
     * Numbered records, blank lines skipped. The number is the physical line the record STARTS on, so it matches the
     * spreadsheet even when a quoted cell contains a line break.
     *
     * @return array<int, array{0: int, 1: array<int, string|null>}>
     */
    private function records(string $content): array
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $records = [];
        $newlines = 0;
        $counted = 0;
        while (true) {
            $start = ftell($handle);
            $fields = fgetcsv($handle, 0, ',', '"', '');
            if ($fields === false) {
                break;
            }

            $newlines += substr_count($content, "\n", $counted, $start - $counted);
            $counted = $start;

            if ($fields === [null]) {
                continue; // a blank line
            }
            $records[] = [$newlines + 1, $fields];
        }
        fclose($handle);

        return $records;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
