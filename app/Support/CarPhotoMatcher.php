<?php

namespace App\Support;

use App\Models\Car;
use Illuminate\Support\Collection;
use Imagick;
use Throwable;

/**
 * Сопоставляет подготовленные фото с машинами сайта.
 *
 * Папка: old_image/{N}_{имя}.jpg — старое фото (такое же, как сейчас на сайте),
 *        new_image/{N}-{что-угодно}.jpg — новое фото той же машины в хорошем качестве.
 * Пара old ↔ new — по номеру N. Old ↔ машина — по совпадению картинки с её текущим фото
 * (перцептивный хеш), а не по имени файла: имена у старых фото бывают любыми («…-avto-4.jpg»).
 */
class CarPhotoMatcher
{
    /** Допустимое отличие нового фото от старого той же пары (из 144 бит хеша). */
    public const PAIR_TOLERANCE = 24;

    /**
     * @return array{matched: list<array{n: int, old: string, new: string, car: Car, pair_distance: int}>, orphans: list<array{n: int, old: string, new: ?string, reason: string}>}
     */
    public static function match(string $dir, ?Collection $cars = null): array
    {
        $old = self::numbered($dir.'/old_image', '_');
        $new = self::numbered($dir.'/new_image', '-');
        $cars ??= Car::query()->get();

        // Хеш текущего фото каждой машины
        $carHashes = [];
        foreach ($cars as $car) {
            $path = self::legacyPath($car);
            if ($path && ($hash = self::hash($path))) {
                $carHashes[$car->id] = $hash;
            }
        }

        $matched = [];
        $orphans = [];
        $taken = [];

        foreach ($old as $n => $oldFile) {
            $newFile = $new[$n] ?? null;
            if (! $newFile) {
                $orphans[] = ['n' => $n, 'old' => $oldFile, 'new' => null, 'reason' => 'нет нового фото с этим номером'];

                continue;
            }

            $oldHash = self::hash($oldFile);
            $pairDistance = self::distance($oldHash, self::hash($newFile));
            if ($pairDistance > self::PAIR_TOLERANCE) {
                $orphans[] = ['n' => $n, 'old' => $oldFile, 'new' => $newFile, 'reason' => "новое фото не похоже на старое (отличие {$pairDistance})"];

                continue;
            }

            $carId = null;
            foreach ($carHashes as $id => $hash) {
                if ($hash === $oldHash && ! isset($taken[$id])) {
                    $carId = $id;
                    break;
                }
            }

            if ($carId === null) {
                $orphans[] = ['n' => $n, 'old' => $oldFile, 'new' => $newFile, 'reason' => 'в каталоге нет машины с таким фото'];

                continue;
            }

            $taken[$carId] = true;
            $matched[] = ['n' => $n, 'old' => $oldFile, 'new' => $newFile, 'car' => $cars->firstWhere('id', $carId), 'pair_distance' => $pairDistance];
        }

        return ['matched' => $matched, 'orphans' => $orphans];
    }

    /**
     * Перцептивный хеш: картинка сжимается до 16×9 в оттенках серого, каждый пиксель — светлее или темнее среднего.
     * Устойчив к пересжатию и масштабу, поэтому 300×167 и 1888×1040 одной машины дают почти одинаковый хеш.
     */
    public static function hash(string $path): ?string
    {
        try {
            $image = new Imagick($path);
            $image->setImageBackgroundColor('white');
            $image = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            $image->resizeImage(16, 9, Imagick::FILTER_BOX, 1);
            $image->transformImageColorspace(Imagick::COLORSPACE_GRAY);
            $pixels = $image->exportImagePixels(0, 0, 16, 9, 'I', Imagick::PIXEL_CHAR);
            $average = array_sum($pixels) / count($pixels);

            return implode('', array_map(fn ($p) => $p > $average ? '1' : '0', $pixels));
        } catch (Throwable) {
            return null;
        }
    }

    public static function distance(?string $a, ?string $b): int
    {
        if ($a === null || $b === null || strlen($a) !== strlen($b)) {
            return PHP_INT_MAX;
        }

        return count(array_filter(str_split($a ^ $b), fn ($x) => $x !== "\0"));
    }

    public static function legacyPath(Car $car): ?string
    {
        if (! $car->legacy_image) {
            return null;
        }
        $path = public_path(html_entity_decode($car->legacy_image, ENT_QUOTES | ENT_HTML5));

        return is_file($path) ? $path : null;
    }

    /**
     * «12_имя.jpg» → [12 => полный путь].
     *
     * @return array<int, string>
     */
    private static function numbered(string $dir, string $separator): array
    {
        $files = [];
        foreach (glob($dir.'/*') ?: [] as $file) {
            if (preg_match('/^(\d+)'.preg_quote($separator, '/').'/', basename($file), $m) && preg_match('/\.(jpe?g|png|webp)$/i', $file)) {
                $files[(int) $m[1]] = $file;
            }
        }
        ksort($files);

        return $files;
    }
}
