<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Состояние фоновой работы сайта: свежесть резервной копии базы и срабатывание планировщика (CRON).
 */
final class SystemHealth
{
    public const SCHEDULER_KEY = 'scheduler:last-run';

    /** Ночная копия делается в 03:30 — сутки плюс запас. */
    private const BACKUP_MAX_AGE_HOURS = 26;

    /** Планировщик срабатывает каждую минуту. */
    private const SCHEDULER_MAX_SILENCE_MINUTES = 5;

    public static function markSchedulerRun(): void
    {
        Cache::forever(self::SCHEDULER_KEY, now()->toIso8601String());
    }

    /** @return array{name: string, size: int, at: CarbonImmutable}|null */
    public static function lastBackup(): ?array
    {
        return Backups::files()->first();
    }

    public static function backupIsFresh(): bool
    {
        $backup = self::lastBackup();

        return $backup !== null && $backup['at']->gt(now()->subHours(self::BACKUP_MAX_AGE_HOURS));
    }

    public static function schedulerLastRun(): ?CarbonImmutable
    {
        $value = Cache::get(self::SCHEDULER_KEY);

        return is_string($value) ? CarbonImmutable::parse($value)->setTimezone(config('app.timezone')) : null;
    }

    public static function schedulerIsAlive(): bool
    {
        $lastRun = self::schedulerLastRun();

        return $lastRun !== null && $lastRun->gt(now()->subMinutes(self::SCHEDULER_MAX_SILENCE_MINUTES));
    }
}
