<?php

namespace App\Support;

use App\Models\Car;
use Illuminate\Support\Facades\Cache;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Размытое превью фото (LQIP): крошечная копия обложки 24×13 px прямо в разметке.
 * Пока настоящее фото грузится, на его месте видна мягкая размытая машина, а не пустой прямоугольник.
 * Делается один раз из готовой миниатюры card и хранится в кеше — без новых конверсий и команд на хостинге.
 */
class Lqip
{
    private const WIDTH = 24;

    public static function forCar(Car $car): ?string
    {
        $media = $car->getFirstMedia('gallery');

        return $media ? self::forMedia($media) : null;
    }

    public static function forMedia(Media $media): ?string
    {
        $key = 'lqip:'.$media->id.':'.$media->updated_at?->timestamp;

        return Cache::rememberForever($key, fn () => self::make($media)) ?: null;
    }

    /** CSS-значение для style="--lqip: …" или пустая строка, если превью нет. */
    public static function style(?string $uri): string
    {
        return $uri ? "--lqip: url('".$uri."')" : '';
    }

    private static function make(Media $media): string
    {
        $path = $media->hasGeneratedConversion('card') ? $media->getPath('card') : $media->getPath();
        if (! is_file($path) || ! function_exists('imagecreatefromstring')) {
            return '';
        }

        $source = @imagecreatefromstring((string) file_get_contents($path));
        if (! $source) {
            return '';
        }

        $height = max(1, (int) round(self::WIDTH * imagesy($source) / max(1, imagesx($source))));
        $tiny = imagecreatetruecolor(self::WIDTH, $height);
        imagecopyresampled($tiny, $source, 0, 0, 0, 0, self::WIDTH, $height, imagesx($source), imagesy($source));

        ob_start();
        function_exists('imagewebp') ? imagewebp($tiny, null, 40) : imagepng($tiny, null, 9);
        $data = (string) ob_get_clean();

        return 'data:image/'.(function_exists('imagewebp') ? 'webp' : 'png').';base64,'.base64_encode($data);
    }
}
