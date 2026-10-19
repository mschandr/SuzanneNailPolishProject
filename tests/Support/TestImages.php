<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;

/** Builds real JPEGs, including ones carrying EXIF metadata, for upload tests. */
class TestImages
{
    public const SECRET_CAMERA = 'SECRETCAMERA9000';

    /** Raw JPEG bytes. With $orientation, an EXIF block (orientation + a camera-make string) is inserted. */
    public static function jpeg(int $width, int $height, ?int $orientation = null, bool $left = true): string
    {
        $im = imagecreatetruecolor($width, $height);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
        // A black square in the top-left corner and a red one in the top-right: lets tests see rotation.
        imagefilledrectangle($im, 0, 0, (int) ($width / 4), (int) ($height / 4), imagecolorallocate($im, 0, 0, 0));
        imagefilledrectangle($im, $width - (int) ($width / 4), 0, $width, (int) ($height / 4), imagecolorallocate($im, 255, 0, 0));
        ob_start();
        imagejpeg($im, null, 90);
        $jpeg = ob_get_clean();

        return $orientation === null ? $jpeg : self::withExif($jpeg, $orientation);
    }

    public static function upload(string $name, int $width, int $height, ?int $orientation = null): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($path, self::jpeg($width, $height, $orientation));

        return new UploadedFile($path, $name, 'image/jpeg', null, true);
    }

    private static function withExif(string $jpeg, int $orientation): string
    {
        $make = self::SECRET_CAMERA."\0";
        $dataOffset = 8 + 2 + 2 * 12 + 4;
        $tiff = 'II'.pack('v', 42).pack('V', 8)
            .pack('v', 2)
            .pack('vvVv', 0x0112, 3, 1, $orientation).pack('v', 0)       // Orientation (SHORT)
            .pack('vvVV', 0x010F, 2, strlen($make), $dataOffset)         // Make (ASCII)
            .pack('V', 0)
            .$make;
        $payload = "Exif\0\0".$tiff;
        $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

        return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
    }
}
