<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stores uploaded images on the public disk as re-encoded JPEGs (strips EXIF/GPS metadata)
 * and creates thumbnails. Uses GD, which is available on practically every cPanel host.
 */
class ImageService
{
    /**
     * @return array{path:string, thumb_path:?string, width:int, height:int}
     */
    public function store(UploadedFile $file, string $dir, bool $withThumb = true): array
    {
        $src = $this->load($file->getRealPath());
        $src = $this->applyExifRotation($src, $file->getRealPath());

        $maxSide = (int) config('maragheh.uploads.max_side', 2048);
        $main = $this->resize($src, $maxSide);
        $name = $dir.'/'.now()->format('Y/m').'/'.Str::random(32);

        $path = $name.'.jpg';
        Storage::disk('public')->put($path, $this->encode($main, 85));
        $width = imagesx($main);
        $height = imagesy($main);

        $thumbPath = null;
        if ($withThumb) {
            $thumb = $this->resize($src, (int) config('maragheh.uploads.thumb_side', 480));
            $thumbPath = $name.'_t.jpg';
            Storage::disk('public')->put($thumbPath, $this->encode($thumb, 80));
            if ($thumb !== $src && $thumb !== $main) {
                imagedestroy($thumb);
            }
        }
        if ($main !== $src) {
            imagedestroy($main);
        }
        imagedestroy($src);

        return ['path' => $path, 'thumb_path' => $thumbPath, 'width' => $width, 'height' => $height];
    }

    public function delete(?string ...$paths): void
    {
        $paths = array_filter($paths);
        if ($paths) {
            Storage::disk('public')->delete($paths);
        }
    }

    private function load(string $path): \GdImage
    {
        $info = @getimagesize($path);
        if (! $info) {
            throw new RuntimeException('Invalid image');
        }
        $img = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            default => false,
        };
        if (! $img) {
            throw new RuntimeException('Unsupported image');
        }
        // flatten transparency onto white for JPEG output
        $w = imagesx($img);
        $h = imagesy($img);
        $canvas = imagecreatetruecolor($w, $h);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $img, 0, 0, 0, 0, $w, $h);
        imagedestroy($img);

        return $canvas;
    }

    private function applyExifRotation(\GdImage $img, string $path): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $img;
        }
        $exif = @exif_read_data($path);
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($angle === 0) {
            return $img;
        }
        $rotated = imagerotate($img, $angle, 0);
        imagedestroy($img);

        return $rotated;
    }

    private function resize(\GdImage $src, int $maxSide): \GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, $maxSide / max($w, $h));
        if ($scale >= 1) {
            return $src;
        }
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $dst;
    }

    private function encode(\GdImage $img, int $quality): string
    {
        imageinterlace($img, true);
        ob_start();
        imagejpeg($img, null, $quality);

        return (string) ob_get_clean();
    }
}
