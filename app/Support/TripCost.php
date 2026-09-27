<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Маршруты-пресеты для строки «Бензин на поездку». Список и километраж — в «Настройки сайта → Поездка».
 * Километры — ориентир по трассам, на сайте подписаны «≈».
 */
class TripCost
{
    /** @return list<array{name: string, km: int, per_day: bool}> */
    public static function routes(): array
    {
        $routes = Setting::get('trip_routes');
        if (! is_array($routes) || ! $routes) {
            $routes = [
                ['name' => 'Обычный отпуск: пляж, магазины, пара поездок', 'km' => 60, 'per_day' => true],
                ['name' => 'Симферополь → Ялта и обратно', 'km' => 170, 'per_day' => false],
                ['name' => 'Симферополь → Ялта → Севастополь → Симферополь', 'km' => 240, 'per_day' => false],
                ['name' => 'Южный берег за 3 дня из Симферополя', 'km' => 400, 'per_day' => false],
                ['name' => 'Только по городу', 'km' => 30, 'per_day' => true],
            ];
        }

        return array_values(array_filter(array_map(fn ($r) => [
            'name' => trim((string) ($r['name'] ?? '')),
            'km' => (int) ($r['km'] ?? 0),
            'per_day' => (bool) ($r['per_day'] ?? false),
        ], $routes), fn ($r) => $r['name'] !== '' && $r['km'] > 0));
    }
}
