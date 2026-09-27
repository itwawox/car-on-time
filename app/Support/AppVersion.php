<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Какая версия кода выложена на этот сайт: коммит, ветка и время выкладки.
 * Файл version.json пишет deploy/release.sh перед заливкой; локально его нет.
 */
final class AppVersion
{
    /**
     * @return array{commit: string, short: string, branch: ?string, deployedAt: ?CarbonImmutable, compareUrl: ?string}|null
     */
    public static function current(): ?array
    {
        $path = (string) config('app.version_file');
        if (! File::exists($path)) {
            return null;
        }

        $data = json_decode((string) File::get($path), true);
        $commit = is_array($data) && is_string($data['commit'] ?? null) ? $data['commit'] : '';
        if (! preg_match('/^[0-9a-f]{7,40}$/', $commit)) {
            return null;
        }

        $branch = is_string($data['branch'] ?? null) && $data['branch'] !== '' ? $data['branch'] : null;
        $repository = rtrim((string) config('app.repository_url'), '/');

        return [
            'commit' => $commit,
            'short' => substr($commit, 0, 7),
            'branch' => $branch,
            'deployedAt' => self::time($data['deployed_at'] ?? null),
            'compareUrl' => $repository !== '' && $branch !== null ? $repository.'/compare/'.$commit.'...'.implode('/', array_map(rawurlencode(...), explode('/', $branch))) : null,
        ];
    }

    private static function time(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->setTimezone((string) config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }
}
