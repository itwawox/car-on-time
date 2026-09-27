<?php

namespace App\Support;

use App\Models\Car;
use App\Models\Setting;

/**
 * Тексты блоков доверия из «Настройки сайта → Доверие». По умолчанию — только то, что уже заявлено
 * в условиях сайта; новых обещаний (например, про замену машины) без админки не добавляем.
 */
class Trust
{
    /** @return list<array{icon: string, title: string, text: string}> */
    public static function steps(): array
    {
        $steps = Setting::get('trust_steps');
        if (is_array($steps) && $steps) {
            return array_values(array_filter(array_map(fn ($s) => [
                'icon' => (string) ($s['icon'] ?? 'check'),
                'title' => trim((string) ($s['title'] ?? '')),
                'text' => trim((string) ($s['text'] ?? '')),
            ], $steps), fn ($s) => $s['title'] !== ''));
        }

        return [
            ['icon' => 'search', 'title' => 'Выберите машину и даты', 'text' => 'Цена за ваш период и доставку считается сразу.'],
            ['icon' => 'chat', 'title' => 'Оставьте заявку', 'text' => 'Без предоплаты — только телефон.'],
            ['icon' => 'clock', 'title' => 'Подтвердим наличие', 'text' => 'Обычно за 15 минут, круглосуточно.'],
            ['icon' => 'car', 'title' => 'Заберите машину', 'text' => 'Оплата при получении, залог вернём после сдачи.'],
        ];
    }

    /** @return array{included: list<string>, extra: list<string>} */
    public static function included(?Car $car = null): array
    {
        $lines = fn (string $key) => array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) Setting::get($key)) ?: [])));
        $km = $car?->daily_km;

        return [
            'included' => $lines('included_list') ?: array_values(array_filter([
                'Страховка ОСАГО и техническое обслуживание',
                'Детские кресла и дополнительный водитель — бесплатно',
                $km ? "{$km} км пробега в сутки, суммируются за все дни" : null,
                'Машина с полным баком',
            ])),
            'extra' => $lines('not_included_list') ?: [
                'Топливо — возвращаете с полным баком',
                'Платные парковки и штрафы',
                'Доставка, если она платная для выбранной точки',
            ],
        ];
    }

    /** @return list<string> способы оплаты; пусто — блок не показываем */
    public static function payments(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) Setting::get('payment_methods')) ?: [])));
    }
}
