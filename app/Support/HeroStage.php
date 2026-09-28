<?php

namespace App\Support;

use App\Models\Car;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * «Сцена» на первом экране главной: машина под выбранную задачу («Для семьи», «В горы»…).
 * Для каждой задачи в «Настройках сайта» можно выбрать машину и загрузить своё фото на прозрачном фоне.
 * Без настроек берётся первая подходящая машина сценария — та же, что на плитке «Для какой поездки?».
 */
class HeroStage
{
    /** Задачи на сцене и короткие подписи вкладок (все шесть не помещаются в одну строку). */
    public const TABS = [
        'family' => 'Для семьи',
        'sea' => 'Город и море',
        'mountains' => 'В горы',
        'business' => 'Бизнес',
        'fun' => 'Кабриолеты',
    ];

    public static function enabled(): bool
    {
        $value = Setting::get('hero_stage_enabled');

        return $value === null || filter_var($value, FILTER_VALIDATE_BOOL);
    }

    /**
     * @param  list<array{key: string, title: string, count: int, price: ?int, cover_id: ?int, url: string}>  $scenarios
     * @return list<array{key: string, tab: string, title: string, count: int, price: ?int, url: string, image: string, alt: string, cutout: bool}>
     */
    public static function slides(array $scenarios): array
    {
        if (! self::enabled()) {
            return [];
        }

        $byKey = collect($scenarios)->keyBy('key');
        $carIds = collect(self::TABS)->keys()
            ->map(fn (string $key) => (int) Setting::get('hero_stage_'.$key.'_car') ?: ($byKey[$key]['cover_id'] ?? null))
            ->filter();
        $cars = Car::query()->published()->whereIn('id', $carIds)->with(['brand', 'media'])->get()->keyBy('id');

        $slides = [];
        foreach (self::TABS as $key => $tab) {
            $scenario = $byKey[$key] ?? null;
            if (! $scenario) {
                continue;
            }

            $car = $cars[(int) Setting::get('hero_stage_'.$key.'_car')] ?? $cars[$scenario['cover_id']] ?? null;
            $upload = Setting::get('hero_stage_'.$key.'_image');
            $image = filled($upload) ? Storage::disk('public')->url($upload) : $car?->coverUrl('large');
            if (! $image) {
                continue;
            }

            $slides[] = [
                'key' => $key,
                'tab' => (string) (Setting::get('hero_stage_'.$key.'_tab') ?: $tab),
                'title' => $scenario['title'],
                'count' => $scenario['count'],
                'price' => $scenario['price'],
                'url' => $scenario['url'],
                'image' => $image,
                'alt' => $car?->displayName() ?? $scenario['title'],
                'cutout' => filled($upload),
            ];
        }

        return $slides;
    }
}
