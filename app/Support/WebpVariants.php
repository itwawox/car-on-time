<?php

namespace App\Support;

use Spatie\Image\Enums\Constraint;
use Spatie\Image\Image;
use Throwable;

/**
 * Готовит webp-копии картинки рядом с оригиналом: photo.jpg → photo-600.webp, photo-1200.webp.
 * Картинка не увеличивается: если оригинал уже, копия получает его ширину.
 */
class WebpVariants
{
    /**
     * @param  list<int>  $widths
     * @return list<string> пути созданных файлов
     */
    public static function make(string $path, array $widths = [600, 1200], int $quality = 80, bool $force = false): array
    {
        if (! is_file($path)) {
            return [];
        }

        $created = [];
        try {
            [$originalWidth] = getimagesize($path) ?: [0];
            foreach ($widths as $width) {
                $target = self::path($path, $width);
                if (! $force && is_file($target) && filemtime($target) >= filemtime($path)) {
                    continue;
                }

                $image = Image::load($path)->format('webp')->quality($quality);
                if ($width > 0 && $originalWidth > $width) {
                    $image->width($width, [Constraint::PreserveAspectRatio, Constraint::DoNotUpsize]);
                }
                $image->save($target);
                $created[] = $target;
            }
        } catch (Throwable $e) {
            report($e);
        }

        return $created;
    }

    /** Ширина 0 — копия в исходном размере: photo.jpg → photo.webp. */
    public static function path(string $path, int $width): string
    {
        return (string) preg_replace('/\.\w+$/', $width > 0 ? "-{$width}.webp" : '.webp', $path);
    }
}
