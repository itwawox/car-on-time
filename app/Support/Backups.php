<?php

namespace App\Support;

use App\Models\BackupRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * Файлы резервных копий базы в storage/backups: список, безопасный путь для скачивания, место на диске.
 */
final class Backups
{
    /** Ночная копия — время для расписания и для страницы «Резервные копии». */
    public const NIGHTLY_AT = '03:30';

    public const KEEP = 14;

    private const FILE_PATTERN = '/^db-[0-9_-]+\.(sql\.gz|sqlite)$/';

    public static function directory(): string
    {
        return (string) config('app.backups_path');
    }

    /**
     * Копии на диске, свежие сверху.
     *
     * @return Collection<int, array{name: string, size: int, at: CarbonImmutable}>
     */
    public static function files(): Collection
    {
        if (! File::isDirectory(self::directory())) {
            return collect();
        }

        return collect(File::files(self::directory()))
            ->filter(fn ($file) => preg_match(self::FILE_PATTERN, $file->getFilename()) === 1)
            ->map(fn ($file) => [
                'name' => $file->getFilename(),
                'size' => (int) $file->getSize(),
                'at' => CarbonImmutable::createFromTimestamp($file->getMTime(), config('app.timezone')),
            ])
            ->sortByDesc(fn (array $file) => $file['at']->getTimestamp())
            ->values();
    }

    /** Полный путь к копии или null, если имя не похоже на копию или файла уже нет. */
    public static function path(?string $name): ?string
    {
        if ($name === null || preg_match(self::FILE_PATTERN, $name) !== 1) {
            return null;
        }

        $path = self::directory().DIRECTORY_SEPARATOR.$name;

        return File::isFile($path) ? $path : null;
    }

    /** Копии, сделанные до появления журнала или вручную на сервере, — в журнал с пометкой «найдена на диске». */
    public static function registerUntracked(): int
    {
        $known = BackupRun::query()->whereNotNull('file')->pluck('file')->flip();

        return self::files()
            ->reject(fn (array $file) => isset($known[$file['name']]))
            ->each(fn (array $file) => BackupRun::query()->create([
                'source' => 'found',
                'status' => 'success',
                'file' => $file['name'],
                'size' => $file['size'],
                'started_at' => $file['at'],
            ]))
            ->count();
    }

    public static function totalSize(): int
    {
        return (int) self::files()->sum('size');
    }

    public static function freeSpace(): ?int
    {
        $free = @disk_free_space(storage_path());

        return $free === false ? null : (int) $free;
    }

    public static function nextNightly(): CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', self::NIGHTLY_AT));
        $next = CarbonImmutable::now()->setTime($hour, $minute);

        return $next->isPast() ? $next->addDay() : $next;
    }

    public static function humanSize(int $bytes): string
    {
        return match (true) {
            $bytes >= 1024 ** 3 => number_format($bytes / 1024 ** 3, 1, ',', ' ').' ГБ',
            $bytes >= 1024 ** 2 => number_format($bytes / 1024 ** 2, 1, ',', ' ').' МБ',
            default => max(1, (int) round($bytes / 1024)).' КБ',
        };
    }
}
