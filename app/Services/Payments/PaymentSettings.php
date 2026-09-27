<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\Setting;

/** Онлайн-предоплата через ЮKassa: настройки со страницы «Интеграции». */
class PaymentSettings
{
    public const MODES = [
        'percent' => 'Процент от суммы заявки',
        'first_day' => 'Стоимость первых суток',
        'fixed' => 'Фиксированная сумма, ₽',
    ];

    /** Ставки НДС ЮKassa (vat_code). */
    public const VAT_CODES = [1 => 'Без НДС', 2 => 'НДС 0%', 3 => 'НДС 10%', 4 => 'НДС 20%', 5 => 'НДС 10/110', 6 => 'НДС 20/120'];

    /** Статусы заявки, при которых клиенту предлагается предоплата: наличие уже подтверждено. */
    public const PAYABLE_STATUSES = ['offered', 'confirmed'];

    public static function enabled(): bool
    {
        return (bool) Setting::get('payments_enabled') && filled(Setting::get('yookassa_shop_id')) && filled(Setting::secret('yookassa_secret_key'));
    }

    public static function mode(): string
    {
        return array_key_exists((string) Setting::get('prepay_mode'), self::MODES) ? (string) Setting::get('prepay_mode') : 'percent';
    }

    public static function value(): int
    {
        return max(0, (int) (Setting::get('prepay_value') ?? 15));
    }

    /** Размер предоплаты по заявке, ₽; не больше суммы заявки. */
    public static function amountFor(Booking $booking): int
    {
        $total = (int) $booking->total;
        $days = max(1, (int) ($booking->quote_snapshot['days'] ?? 1));

        $amount = match (self::mode()) {
            'fixed' => self::value(),
            'first_day' => (int) ceil($total / $days),
            default => (int) ceil($total * min(100, self::value()) / 100),
        };

        return max(0, min($amount, $total));
    }

    public static function receiptsEnabled(): bool
    {
        return (bool) Setting::get('yookassa_receipt');
    }

    public static function vatCode(): int
    {
        $code = (int) Setting::get('yookassa_vat_code', 1);

        return array_key_exists($code, self::VAT_CODES) ? $code : 1;
    }
}
