<?php

namespace App\Services\Sms;

use App\Models\Setting;

/** SMS-уведомления клиенту: провайдер, отправитель, шаблоны со страницы «Интеграции». */
class SmsSettings
{
    public const PROVIDERS = ['smsru' => 'SMS.ru', 'smsc' => 'SMSC.ru'];

    /** Шаблоны по умолчанию; в админке их можно переписать. */
    public const TEMPLATES = [
        'created' => [
            'label' => 'Заявка принята',
            'default' => '{brand}: заявка №{id} принята. Уточним наличие {car} и перезвоним в течение 15 минут. Статус: {link}',
        ],
        'confirmed' => [
            'label' => 'Бронь подтверждена',
            'default' => 'Бронь №{id} подтверждена: {car}, {date} в {time}, {place}. Возьмите паспорт и права. {link}',
        ],
        'declined' => [
            'label' => 'Отказ',
            'default' => 'Заявка №{id}: {car} на эти даты занята. Подберём замену — позвоните {phone} или напишите нам.',
        ],
        'reminder' => [
            'label' => 'Напоминание за сутки до выдачи',
            'default' => 'Напоминаем: завтра в {time} выдача {car}, {place}. Паспорт и права с собой. {link}',
        ],
        'review' => [
            'label' => 'Просьба оставить отзыв после возврата',
            'default' => 'Спасибо, что выбрали {brand}! Расскажите, как прошла поездка — это 1 минута: {review_link}',
        ],
    ];

    public const PLACEHOLDERS = '{brand} {id} {car} {date} {time} {place} {total} {phone} {link} {review_link}';

    public static function enabled(): bool
    {
        return (bool) Setting::get('sms_enabled') && self::credentialsFilled();
    }

    public static function provider(): string
    {
        return array_key_exists((string) Setting::get('sms_provider'), self::PROVIDERS) ? (string) Setting::get('sms_provider') : 'smsru';
    }

    public static function credentialsFilled(): bool
    {
        return self::provider() === 'smsc'
            ? filled(Setting::get('sms_login')) && filled(Setting::secret('sms_password'))
            : filled(Setting::secret('sms_api_key'));
    }

    /** Какие события включены: по умолчанию все. */
    public static function eventEnabled(string $template): bool
    {
        $events = Setting::get('sms_events');

        return ! is_array($events) || in_array($template, $events, true);
    }

    public static function template(string $key): string
    {
        return (string) (Setting::get('sms_tpl_'.$key) ?: self::TEMPLATES[$key]['default']);
    }
}
