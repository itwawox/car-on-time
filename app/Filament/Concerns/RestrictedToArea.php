<?php

namespace App\Filament\Concerns;

use App\Models\User;

/**
 * Раздел админки доступен только ролям, которым открыта его область (см. User::AREAS).
 * Класс, подключающий трейт, объявляет область через accessArea().
 */
trait RestrictedToArea
{
    abstract protected static function accessArea(): string;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->canUseArea(static::accessArea());
    }
}
