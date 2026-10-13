<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Safe image storage: the upload is decoded and RE-ENCODED with GD, which
 * strips EXIF/GPS metadata and anything that is not pixels (polyglot files,
 * embedded scripts). Stored on the private disk under a random name.
 */
final class ImageStore
{
    /** @return string path on the private disk */
    public static function storeResized(UploadedFile $file, string $dir, int $maxEdge, int $quality = 82): string
    {
        [$img, $w, $h] = self::load($file->getRealPath());
        $scaled = self::scale($img, $w, $h, $maxEdge);
        $path = trim($dir, '/').'/'.Str::random(32).'.jpg';
        Storage::disk('private')->put($path, self::encode($scaled, $quality));
        imagedestroy($img);
        if ($scaled !== $img) {
            imagedestroy($scaled);
        }

        return $path;
    }

    /** @return array{path: string, thumb: string, width: int, height: int} */
    public static function storeWithThumb(UploadedFile $file, string $dir): array
    {
        [$img, $w, $h] = self::load($file->getRealPath());
        $name = Str::random(32);
        $full = self::scale($img, $w, $h, 2200);
        $thumb = self::scale($img, $w, $h, 640);
        $path = trim($dir, '/')."/{$name}.jpg";
        $thumbPath = trim($dir, '/')."/{$name}_t.jpg";
        Storage::disk('private')->put($path, self::encode($full, 85));
        Storage::disk('private')->put($thumbPath, self::encode($thumb, 78));

        return ['path' => $path, 'thumb' => $thumbPath, 'width' => imagesx($full), 'height' => imagesy($full)];
    }

    private static function load(string $file): array
    {
        $info = @getimagesize($file);
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new RuntimeException('Unsupported image.');
        }
        if ($info[0] * $info[1] > 50_000_000) {
            throw new RuntimeException('Image too large.');
        }
        $img = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file),
            IMAGETYPE_PNG => @imagecreatefrompng($file),
            IMAGETYPE_WEBP => @imagecreatefromwebp($file),
        };
        if (! $img) {
            throw new RuntimeException('Could not read image.');
        }
        // Respect phone camera orientation before the EXIF is discarded.
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $o = @exif_read_data($file)['Orientation'] ?? 1;
            $img = match ((int) $o) {
                3 => imagerotate($img, 180, 0), 6 => imagerotate($img, -90, 0), 8 => imagerotate($img, 90, 0), default => $img
            };
        }

        return [$img, imagesx($img), imagesy($img)];
    }

    private static function scale($img, int $w, int $h, int $maxEdge)
    {
        // Always redraw onto a white canvas (flattens PNG transparency for JPEG output).
        $ratio = min(1, $maxEdge / max($w, $h));
        $nw = (int) round($w * $ratio);
        $nh = (int) round($h * $ratio);
        $out = imagecreatetruecolor($nw, $nh);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $out;
    }

    private static function encode($img, int $quality): string
    {
        ob_start();
        imagejpeg($img, null, $quality);

        return (string) ob_get_clean();
    }
}
