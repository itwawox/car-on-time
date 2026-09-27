<?php

namespace App\Support;

use App\Models\Car;
use App\Models\Setting;
use App\Support\Seo\SeoSettings;

/**
 * Описание машины из справочника модели и её характеристик.
 *
 * Абзацы: о модели → эта машина (коробка, привод, топливо, места) → для каких поездок в Крыму → условия.
 * Фразы берутся из «SEO → Настройки SEO → Описания машин»; вариант выбирается по id машины,
 * поэтому у шести Solaris тексты разные, но у одной машины — стабильные.
 */
class CarDescription
{
    public static function build(Car $car): string
    {
        $car->loadMissing(['carModel', 'bodyType', 'classes', 'prices']);
        $model = $car->carModel;
        $seed = $car->id;
        $pick = fn (string $key) => SeoSettings::pick($key, $seed);
        $vars = self::vars($car);
        $fill = fn (?string $text) => $text ? SeoSettings::fill($text, $vars) : null;

        $paragraphs = [];

        // 1. О модели
        if ($model?->overview) {
            $years = $car->yearsLabel();
            $paragraphs[] = trim(strip_tags($model->overview).($years
                ? ($car->year_to && $car->year_to !== $car->year_from ? " В прокате — машины {$years} годов выпуска." : " Год выпуска — {$years}.")
                : ''));
        }

        // 2. Эта машина: коробка, привод, топливо, места
        $electric = $car->fuel === 'electric';
        $spec = array_filter([
            $electric ? 'электропривод без коробки передач (управление как у автомата)'
                : ($car->gearbox === 'mt' ? 'механическая коробка передач' : 'автоматическая коробка передач'),
            self::drivePhrase($car),
            $model?->fuel_note ?: ($car->fuelLabel() ? mb_strtolower($car->fuelLabel()) : null),
            $car->engine ? 'двигатель '.$car->engine : null,
            $car->seats ? $car->seats.' '.self::plural($car->seats, 'место', 'места', 'мест') : null,
        ]);
        $paragraphs[] = trim($car->name.': '.implode(', ', $spec).'. '.implode(' ', array_filter([
            $electric ? null : $pick($car->gearbox === 'mt' ? 'gearbox_mt' : 'gearbox_at'),
            $car->drivetrain ? $pick('drive_'.$car->drivetrain) : null,
        ])));

        // 3. Для каких поездок в Крыму
        $scenarios = array_filter([
            $car->bodyType ? $pick('body_'.$car->bodyType->slug) : null,
            ($class = self::mainClass($car)) ? $pick('class_'.$class) : null,
            $car->seats >= 6 ? $fill($pick('family')) : null,
            $car->fuel === 'electric' && $model?->fuel_note ? $pick('electric') : null,
        ]);
        if ($model?->strengths) {
            $scenarios[] = 'Сильные стороны модели: '.implode(', ', array_map('mb_lcfirst', $model->strengths)).'.';
        }
        if ($scenarios) {
            $paragraphs[] = implode(' ', $scenarios);
        }

        // 4. Условия аренды — только из полей машины
        if ($conditions = $fill($pick('conditions'))) {
            $paragraphs[] = $conditions;
        }

        return implode("\n", array_map(fn ($p) => '<p>'.e($p).'</p>', array_filter($paragraphs)));
    }

    /**
     * @return array<string, mixed>
     */
    public static function vars(Car $car): array
    {
        $pickup = Setting::get('pickup_point');

        return [
            'name' => $car->name,
            'price' => $car->currentPriceFrom(),
            'seats' => $car->seats,
            'age' => $car->min_age ?: Setting::get('min_age', 22),
            'experience' => $car->min_experience ?: Setting::get('min_experience', 2),
            'deposit' => $car->deposit ? ', залог '.number_format((int) $car->deposit, 0, ',', ' ').' ₽ возвращается после сдачи машины' : '',
            'min_days' => $car->min_days > 1 ? '. Минимальный срок — '.$car->min_days.' '.self::plural((int) $car->min_days, 'сутки', 'суток', 'суток') : '',
            'daily_km' => $car->daily_km ? ', в сутки входит '.$car->daily_km.' км пробега' : '',
            'pickup' => $pickup ? mb_lcfirst($pickup) : 'в аэропорту Симферополь',
        ];
    }

    /**
     * «передний привод (полный — в версиях 4WD)», «полный привод xDrive», «постоянный полный привод…».
     * Примечание модели показывается, только если привод машины совпадает с приводом модели.
     */
    private static function drivePhrase(Car $car): ?string
    {
        if (! $label = $car->drivetrainLabel()) {
            return null;
        }

        $phrase = mb_strtolower($label).' привод';
        $note = $car->carModel?->drivetrain === $car->drivetrain ? $car->carModel?->drivetrain_note : null;
        if (! $note) {
            return $phrase;
        }

        return mb_stripos($note, $phrase) !== false ? $note : $phrase.' ('.$note.')';
    }

    /** Класс для текста: бизнес важнее среднего, эконом — для недорогих машин из «эконом + средний». */
    private static function mainClass(Car $car): ?string
    {
        $slugs = $car->classes->pluck('slug');

        return collect(['biznes', 'ekonom', 'srednij'])->first(fn ($slug) => $slugs->contains($slug));
    }

    /** Старый шаблонный текст из импорта — его можно перезаписывать без --force. */
    public static function isGeneric(?string $text): bool
    {
        $plain = trim(strip_tags((string) $text));

        return $plain === '' || (bool) preg_match('/^Аренда .+ в Крыму от Car on Time\. Наличие подтвердим/u', $plain);
    }

    private static function plural(int $n, string $one, string $few, string $many): string
    {
        $n10 = $n % 10;
        $n100 = $n % 100;

        return match (true) {
            $n10 === 1 && $n100 !== 11 => $one,
            $n10 >= 2 && $n10 <= 4 && ($n100 < 12 || $n100 > 14) => $few,
            default => $many,
        };
    }
}
