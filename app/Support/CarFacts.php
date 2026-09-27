<?php

namespace App\Support;

use App\Models\Car;

/**
 * Числовые характеристики машины для подбора, сравнения и расчёта бензина.
 *
 * Приоритет: точное значение в карточке машины → типичное для модели (справочник).
 * Если значение взято из справочника, isTypical('power') === true — на сайте это «≈ типично для модели».
 * Дизельные машины берут дизельную версию модели, электро — расход в кВт·ч.
 */
class CarFacts
{
    public const GRADES = ['92' => 'АИ-92', '95' => 'АИ-95', '98' => 'АИ-98', 'dt' => 'ДТ', 'electric' => 'электричество'];

    /** Цены по умолчанию, пока в настройках не указаны свои (₽ за литр / кВт·ч). */
    public const DEFAULT_PRICES = ['92' => 62, '95' => 68, '98' => 85, 'dt' => 74, 'electric' => 25];

    public readonly ?int $power;

    public readonly ?int $powerMax;

    public readonly ?float $engineL;

    public readonly ?float $consumption;

    public readonly ?float $consumptionCity;

    public readonly ?float $consumptionHighway;

    public readonly ?int $trunk;

    public readonly ?int $clearance;

    public readonly ?int $tank;

    public readonly ?string $grade;

    /** @var array<string, bool> */
    private array $typical = [];

    public function __construct(public readonly Car $car)
    {
        $model = $car->carModel;
        $diesel = $car->fuel === 'diesel' && $model?->diesel_power_hp;

        $pick = function (string $key, mixed $own, mixed $fromModel) {
            if ($own !== null && $own !== '') {
                return $own;
            }
            $this->typical[$key] = $fromModel !== null;

            return $fromModel;
        };

        $this->power = self::int($pick('power', $car->power_hp, $diesel ? $model->diesel_power_hp : $model?->power_hp));
        $this->powerMax = $car->power_hp || $diesel ? null : self::int($model?->power_hp_max);
        $this->engineL = self::float($pick('engine', $car->engine_l, $diesel ? $model->diesel_engine_l : $model?->engine_l));
        $this->consumption = self::float($pick('consumption', $car->consumption_mixed, $diesel ? $model->diesel_consumption : $model?->consumption_mixed));
        $this->consumptionCity = $diesel || $car->consumption_mixed ? null : self::float($model?->consumption_city);
        $this->consumptionHighway = $diesel || $car->consumption_mixed ? null : self::float($model?->consumption_highway);
        $this->trunk = self::int($pick('trunk', $car->trunk_l, $model?->trunk_l));
        $this->clearance = self::int($pick('clearance', $car->clearance_mm, $model?->clearance_mm));
        $this->tank = self::int($model?->tank_l);
        $this->grade = match (true) {
            $car->fuel === 'diesel' => 'dt',
            $car->fuel === 'electric' => 'electric',
            default => $model?->fuel_grade ?: ($car->fuel === 'petrol' ? '92' : null),
        };
    }

    public static function of(Car $car): self
    {
        return $car->facts();
    }

    public function isTypical(string $key): bool
    {
        return $this->typical[$key] ?? false;
    }

    /** Хоть одно значение из справочника — для общей пометки «≈ типично для модели». */
    public function hasTypical(): bool
    {
        return in_array(true, $this->typical, true);
    }

    public function isElectric(): bool
    {
        return $this->grade === 'electric';
    }

    public function unit(): string
    {
        return $this->isElectric() ? 'кВт·ч' : 'л';
    }

    public function gradeLabel(): ?string
    {
        return self::GRADES[$this->grade] ?? null;
    }

    /** Цена литра (или кВт·ч) из «Настройки сайта → Поездка». */
    public function fuelPrice(): ?float
    {
        return Fleet::fuelPrice($this->grade);
    }

    /** Сколько стоит проехать 100 км, ₽. */
    public function costPer100km(): ?int
    {
        $price = $this->fuelPrice();

        return $this->consumption && $price ? (int) round($this->consumption * $price) : null;
    }

    public function fuelCost(int $km): ?int
    {
        $per100 = $this->costPer100km();

        return $per100 !== null ? (int) round($per100 * $km / 100) : null;
    }

    public function powerLabel(): ?string
    {
        if (! $this->power) {
            return null;
        }

        return $this->powerMax && $this->powerMax > $this->power
            ? $this->power.'–'.$this->powerMax.' л.с.'
            : $this->power.' л.с.';
    }

    public function consumptionLabel(): ?string
    {
        return $this->consumption ? self::num($this->consumption).' '.$this->unit().'/100 км' : null;
    }

    public function engineLabel(): ?string
    {
        if ($this->car->engine && ! $this->car->power_hp && ! $this->car->engine_l) {
            return $this->car->engine;
        }

        return $this->engineL ? self::num($this->engineL).' л' : null;
    }

    /** Мощность — человеческим языком. */
    public function powerHint(): ?string
    {
        return match (true) {
            ! $this->power => null,
            $this->power < 100 => 'спокойная, для города',
            $this->power < 150 => 'уверенно в городе и на трассе',
            $this->power < 220 => 'динамичная, легко обгоняет',
            default => 'очень мощная',
        };
    }

    /** Багажник — человеческим языком. */
    public function trunkHint(): ?string
    {
        return match (true) {
            ! $this->trunk => null,
            $this->trunk < 300 => 'сумка и пара рюкзаков',
            $this->trunk < 420 => '1–2 чемодана',
            $this->trunk < 520 => '2 больших чемодана и сумки',
            $this->trunk < 800 => '3 чемодана или коляска с вещами',
            default => 'много багажа',
        };
    }

    public function clearanceHint(): ?string
    {
        return match (true) {
            ! $this->clearance => null,
            $this->clearance >= 190 => 'не боится грунтовок и бордюров',
            $this->clearance >= 160 => 'обычный городской',
            default => 'низкий — аккуратнее с бордюрами',
        };
    }

    public static function num(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, ',', ''), '0'), ',');
    }

    private static function int(mixed $v): ?int
    {
        return $v === null || $v === '' ? null : (int) $v;
    }

    private static function float(mixed $v): ?float
    {
        return $v === null || $v === '' ? null : (float) $v;
    }
}
