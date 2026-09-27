<?php

namespace App\Services\Bitrix24;

use App\Models\Setting;

/** Настройки интеграции со страницы «Интеграции» в админке. */
class Bitrix24Settings
{
    /** Поля брони, которые можно передать в пользовательские поля CRM. */
    public const BOOKING_FIELDS = [
        'booking_id' => 'Номер заявки',
        'car' => 'Машина',
        'car_class' => 'Класс машины',
        'starts_at' => 'Начало аренды',
        'ends_at' => 'Окончание аренды',
        'days' => 'Суток',
        'pickup' => 'Место выдачи',
        'return' => 'Место возврата',
        'extras' => 'Доп. услуги',
        'promo_code' => 'Промокод',
        'total' => 'Сумма',
        'deposit' => 'Залог',
        'messengers' => 'Удобные мессенджеры',
        'source' => 'Откуда заявка на сайте',
        'admin_url' => 'Ссылка на заявку в админке',
    ];

    /** Поля обращения (перезвонить, юрлицо, сдать авто). */
    public const LEAD_FIELDS = [
        'lead_id' => 'Номер обращения',
        'type' => 'Тип обращения',
        'company' => 'Компания',
        'inn' => 'ИНН',
        'message' => 'Сообщение',
        'details' => 'Подробности (машина владельца и т. п.)',
        'page' => 'Страница сайта',
        'admin_url' => 'Ссылка на обращение в админке',
    ];

    public const ENTITIES = ['deal' => 'Сделка (с контактом)', 'lead' => 'Лид'];

    public static function enabled(): bool
    {
        return (bool) Setting::get('bitrix_enabled') && self::webhookUrl() !== null;
    }

    public static function webhookUrl(): ?string
    {
        return Setting::secret('bitrix_webhook_url');
    }

    /** Что создаём для брони: сделку или лид. */
    public static function bookingEntity(): string
    {
        return Setting::get('bitrix_booking_entity') === 'lead' ? 'lead' : 'deal';
    }

    /** Что создаём для обращения: лид или сделку. */
    public static function leadEntity(): string
    {
        return Setting::get('bitrix_lead_entity') === 'deal' ? 'deal' : 'lead';
    }

    public static function categoryId(): int
    {
        return (int) Setting::get('bitrix_category_id', 0);
    }

    public static function assignedById(): ?int
    {
        $id = (int) Setting::get('bitrix_assigned_by_id');

        return $id > 0 ? $id : null;
    }

    public static function sourceId(): ?string
    {
        return Setting::get('bitrix_source_id') ?: null;
    }

    /** Стадия сделки (или статус лида) для статуса брони на сайте. */
    public static function stageFor(string $bookingStatus): ?string
    {
        return (self::stageMap()[$bookingStatus] ?? null) ?: null;
    }

    /** Обратное соответствие: стадия в CRM → статус брони. */
    public static function statusForStage(string $stage): ?string
    {
        $status = array_search($stage, array_filter(self::stageMap()), true);

        return $status === false ? null : (string) $status;
    }

    /** @return array<string, string> */
    public static function stageMap(): array
    {
        return array_filter((array) Setting::get('bitrix_stage_map', []), 'is_string');
    }

    /**
     * Соответствие «поле сайта → пользовательское поле CRM».
     *
     * @return array<string, string>
     */
    public static function fieldMap(string $kind): array
    {
        $rows = (array) Setting::get($kind === 'lead' ? 'bitrix_lead_field_map' : 'bitrix_booking_field_map', []);

        return collect($rows)
            ->filter(fn ($row) => filled($row['site'] ?? null) && filled($row['crm'] ?? null))
            ->mapWithKeys(fn ($row) => [(string) $row['site'] => (string) $row['crm']])
            ->all();
    }

    public static function inboundEnabled(): bool
    {
        return self::enabled() && (bool) Setting::get('bitrix_inbound_enabled') && self::inboundToken() !== null;
    }

    public static function inboundToken(): ?string
    {
        return Setting::secret('bitrix_inbound_token');
    }
}
