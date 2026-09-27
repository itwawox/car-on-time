<?php

namespace App\Support\Search;

/**
 * Разбирает запрос на текст и понятные фильтры:
 * «киа рио автомат до 3000» → текст «киа рио», коробка AT, цена ≤ 3000.
 *
 * Все слова-фильтры редактируются в Filament («Умный поиск → Настройки → Слова-фильтры»).
 */
class QueryIntent
{
    /** @var list<string> */
    public array $terms = [];

    public ?string $gearbox = null;

    public ?int $priceMax = null;

    public ?int $priceMin = null;

    public ?int $seatsMin = null;

    public ?string $drivetrain = null;

    public ?string $fuel = null;

    public bool $cheapFirst = false;

    /** @var array<string, array{0: string, 1: mixed}>|null слово → [действие, значение] */
    private static ?array $lexicon = null;

    public static function parse(string $query): self
    {
        $intent = new self;
        $lexicon = self::lexicon();
        $tokens = TextNormalizer::tokens($query);
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $t = $tokens[$i];
            $next = $tokens[$i + 1] ?? null;
            [$action, $value] = $lexicon[$t] ?? [null, null];

            // «до 3000», «дешевле 3к», «от 2000»
            if ($action === 'price_max' && $next !== null && ($n = self::money($next)) !== null) {
                $intent->priceMax = $n;
                $i++;

                continue;
            }
            if ($action === 'price_min' && $next !== null && ($n = self::money($next)) !== null && $n >= 500) {
                $intent->priceMin = $n;
                $i++;

                continue;
            }

            // «7 мест», «7 местный», «7местный»
            if (ctype_digit($t) && (int) $t >= 2 && (int) $t <= 9 && $next !== null && preg_match('/^(мест|seat)/u', $next)) {
                $intent->seatsMin = (int) $t;
                $i++;

                continue;
            }
            if (preg_match('/^(\d)местн/u', $t, $m)) {
                $intent->seatsMin = (int) $m[1];

                continue;
            }

            match ($action) {
                'seats' => $intent->seatsMin = (int) $value,
                'gearbox' => $intent->gearbox = $value,
                'drivetrain' => $intent->drivetrain = $value,
                'fuel' => $intent->fuel = $value,
                'cheap' => $intent->cheapFirst = true,
                default => null,
            };

            // Слово-фильтр, стоп-слово («до» без числа тоже) или одиночная буква
            // (хвосты от «a/t», «м/т») в текстовый поиск не идут
            if ($action !== null || (mb_strlen($t) === 1 && ! ctype_digit($t))) {
                continue;
            }

            $intent->terms[] = $t;
        }

        return $intent;
    }

    /** Сброс словаря после сохранения настроек. */
    public static function flush(): void
    {
        self::$lexicon = null;
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    private static function lexicon(): array
    {
        if (self::$lexicon !== null) {
            return self::$lexicon;
        }

        $dict = SearchSettings::intent();
        $map = [];
        $add = function (iterable $words, string $action, mixed $value = null) use (&$map) {
            foreach ($words as $word) {
                foreach (TextNormalizer::tokens((string) $word) as $token) {
                    $map[$token] ??= [$action, $value];
                }
            }
        };

        // Порядок важен: фильтры раньше стоп-слов
        $add($dict['price_max_words'] ?? [], 'price_max');
        $add($dict['price_min_words'] ?? [], 'price_min');
        $add($dict['gearbox_at'] ?? [], 'gearbox', 'at');
        $add($dict['gearbox_mt'] ?? [], 'gearbox', 'mt');
        $add($dict['drive_4wd'] ?? [], 'drivetrain', '4wd');
        $add($dict['fuel_electric'] ?? [], 'fuel', 'electric');
        $add($dict['fuel_diesel'] ?? [], 'fuel', 'diesel');
        $add($dict['cheap'] ?? [], 'cheap');
        foreach ((array) ($dict['seat_words'] ?? []) as $word => $seats) {
            $add([$word], 'seats', (int) $seats);
        }
        $add($dict['stopwords'] ?? [], 'stop');

        return self::$lexicon = $map;
    }

    /** «3000», «3к», «3тыс» → 3000. */
    private static function money(string $token): ?int
    {
        if (preg_match('/^(\d+)(к|k|тыс|тысяч)?$/u', $token, $m)) {
            $n = (int) $m[1];

            return ! empty($m[2]) || $n < 100 ? $n * 1000 : $n;
        }

        return null;
    }

    public function hasFilters(): bool
    {
        return $this->gearbox || $this->priceMax || $this->priceMin || $this->seatsMin || $this->drivetrain || $this->fuel;
    }

    /**
     * Что поиск «понял» — для чипов в интерфейсе. Подписи редактируются в Filament.
     *
     * @return list<array{key: string, label: string}>
     */
    public function chips(): array
    {
        $price = fn (int $n) => number_format($n, 0, ',', ' ');
        $text = SearchSettings::text(...);

        return array_values(array_filter([
            $this->gearbox ? ['key' => 'gearbox', 'label' => $text($this->gearbox === 'at' ? 'chip_at' : 'chip_mt')] : null,
            $this->priceMax ? ['key' => 'price_max', 'label' => $text('chip_price_max', ['price' => $price($this->priceMax)])] : null,
            $this->priceMin ? ['key' => 'price_min', 'label' => $text('chip_price_min', ['price' => $price($this->priceMin)])] : null,
            $this->seatsMin ? ['key' => 'seats', 'label' => $text('chip_seats', ['seats' => $this->seatsMin])] : null,
            $this->drivetrain ? ['key' => 'drivetrain', 'label' => $text('chip_4wd')] : null,
            $this->fuel ? ['key' => 'fuel', 'label' => $text('chip_'.$this->fuel)] : null,
            $this->cheapFirst ? ['key' => 'cheap', 'label' => $text('chip_cheap')] : null,
        ]));
    }
}
