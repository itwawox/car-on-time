<?php

namespace App\Support;

use App\Models\Setting;

/** Этапы заявки для клиента: подписи редактируются в «Настройках сайта». */
class BookingStages
{
    /** Шкала трекера по порядку; «Отказ» показывается отдельно. */
    public const TRACK = ['received', 'checking', 'confirmed', 'active', 'done'];

    public const DEFAULTS = [
        'received' => ['Заявка принята', 'Уточняем наличие у владельца — обычно это занимает до 15 минут.'],
        'checking' => ['Проверяем наличие', 'Менеджер уже занимается заявкой и скоро свяжется с вами.'],
        'confirmed' => ['Бронь подтверждена', 'Машина закреплена за вами. Возьмите паспорт и водительское удостоверение.'],
        'active' => ['Машина у вас', 'Хорошей дороги! Если что-то случится — звоните, мы на связи круглосуточно.'],
        'done' => ['Аренда завершена', 'Спасибо, что выбрали нас. Будем рады отзыву о поездке.'],
        'declined' => ['Машина недоступна', 'На эти даты машина занята. Подберём такую же или похожую — позвоните или напишите нам.'],
    ];

    public static function title(string $stage): string
    {
        return (string) (Setting::get('stage_'.$stage.'_title') ?: self::DEFAULTS[$stage][0]);
    }

    public static function text(string $stage): string
    {
        return (string) (Setting::get('stage_'.$stage.'_text') ?: self::DEFAULTS[$stage][1]);
    }
}
