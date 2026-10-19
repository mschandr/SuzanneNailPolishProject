<?php

namespace App\Support;

use GdImage;
use RuntimeException;

/**
 * Turns an uploaded picture into a clean JPEG: phone orientation applied, every metadata block
 * (EXIF, GPS, camera) dropped by re-encoding, scaled down for the web, plus a small thumbnail.
 */
class PhotoProcessor
{
    public const FULL_EDGE = 1600;

    public const THUMB_EDGE = 400;

    /** Decoding allocates ~4 bytes per pixel, so cap the pixel count well inside PHP's memory limit. */
    public const MAX_PIXELS = 40_000_000;

    /** @return array{0: int, 1: int}|null width and height, or null when it isn't a readable JPEG/PNG/WebP */
    public static function dimensions(string $path): ?array
    {
        $info = @getimagesize($path);

        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return null;
        }

        return [$info[0], $info[1]];
    }

    /** @return array{full: string, thumb: string} JPEG bytes */
    public function process(string $path): array
    {
        $this->allowMemoryForDecoding();

        $decoded = $this->load(file_get_contents($path));
        $orientation = $this->exifOrientation($path);

        // Shrink first: rotating or flattening a 40-megapixel bitmap would hold several huge copies
        // in memory at once, while the same steps on a 1600px image cost almost nothing.
        $image = $this->fit($decoded, self::FULL_EDGE);
        unset($decoded);

        return $this->encode($this->applyOrientation($image, $orientation));
    }

    /** @return array{full: string, thumb: string} */
    public function rotate(string $jpegBytes, int $degreesClockwise): array
    {
        $image = $this->load($jpegBytes);

        // imagerotate turns counter-clockwise, so a clockwise quarter-turn is a negative angle.
        $rotated = imagerotate($image, -$degreesClockwise, 0);

        if ($rotated === false) {
            throw new RuntimeException('Could not rotate the image.');
        }

        return $this->encode($rotated);
    }

    /** A decoded bitmap is ~4 bytes per pixel; make sure the largest allowed photo fits, for this request only. */
    private function allowMemoryForDecoding(): void
    {
        $limit = ini_get('memory_limit');

        if ($limit !== '-1' && $this->bytes($limit) < 384 * 1024 * 1024) {
            @ini_set('memory_limit', '384M');
        }
    }

    private function bytes(string $value): int
    {
        $number = (int) $value;

        return match (strtolower(substr(trim($value), -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private function load(string $bytes): GdImage
    {
        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            throw new RuntimeException('Could not read the image.');
        }

        return $image;
    }

    private function exifOrientation(string $path): int
    {
        if (! function_exists('exif_read_data') || @exif_imagetype($path) !== IMAGETYPE_JPEG) {
            return 1;
        }

        $exif = @exif_read_data($path);

        return (int) ($exif['Orientation'] ?? 1);
    }

    /** EXIF orientations 2-8 are mirrors and quarter-turns of how the sensor stored the picture. */
    private function applyOrientation(GdImage $image, int $orientation): GdImage
    {
        $turn = fn (GdImage $im, int $deg) => imagerotate($im, $deg, 0) ?: $im;

        switch ($orientation) {
            case 2: imageflip($image, IMG_FLIP_HORIZONTAL);
                return $image;
            case 3: return $turn($image, 180);
            case 4: imageflip($image, IMG_FLIP_VERTICAL);
                return $image;
            case 5: imageflip($image, IMG_FLIP_HORIZONTAL);
                return $turn($image, 90);
            case 6: return $turn($image, -90);
            case 7: imageflip($image, IMG_FLIP_HORIZONTAL);
                return $turn($image, -90);
            case 8: return $turn($image, 90);
            default: return $image;
        }
    }

    /** @return array{full: string, thumb: string} */
    private function encode(GdImage $image): array
    {
        $image = $this->flattenOnWhite($image);

        return [
            'full' => $this->jpeg($this->fit($image, self::FULL_EDGE), 85),
            'thumb' => $this->jpeg($this->fit($image, self::THUMB_EDGE), 80),
        ];
    }

    /** JPEG has no transparency; a transparent PNG would otherwise turn black. */
    private function flattenOnWhite(GdImage $image): GdImage
    {
        $flat = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        return $flat;
    }

    /** Scale so the long edge is at most $edge. Never enlarges. */
    private function fit(GdImage $image, int $edge): GdImage
    {
        [$w, $h] = [imagesx($image), imagesy($image)];
        $long = max($w, $h);

        if ($long <= $edge) {
            return $image;
        }

        $ratio = $edge / $long;
        $nw = max(1, (int) round($w * $ratio));
        $nh = max(1, (int) round($h * $ratio));
        $out = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($out, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $out;
    }

    private function jpeg(GdImage $image, int $quality): string
    {
        ob_start();
        imagejpeg($image, null, $quality);

        return (string) ob_get_clean();
    }
}
