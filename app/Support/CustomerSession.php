<?php

namespace App\Support;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Builder;

/**
 * Вход клиента в личный кабинет без пароля: по коду из SMS или по ссылке со страницы заявки.
 * Клиент — это номер телефона; в сессии хранится только он.
 */
class CustomerSession
{
    private const KEY = 'customer_phone';

    public static function digits(string $phone): string
    {
        return ltrim(Phone::e164($phone), '+');
    }

    public static function login(string $phone): void
    {
        session()->regenerate();
        session()->put(self::KEY, self::digits($phone));
    }

    public static function logout(): void
    {
        session()->forget(self::KEY);
        session()->regenerate();
    }

    public static function phone(): ?string
    {
        return session(self::KEY);
    }

    /** Брони текущего клиента. */
    public static function bookings(): Builder
    {
        return Booking::query()->where('phone_digits', self::phone() ?? '-');
    }

    public static function owns(Booking $booking): bool
    {
        return self::phone() !== null && $booking->phone_digits === self::phone();
    }
}
