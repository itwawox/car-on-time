<?php

namespace App\Support\Search;

use App\Models\SearchSynonym;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Настройки умного поиска. Всё редактируется в Filament («Умный поиск → Настройки»),
 * хранится в settings (group = search). DEFAULTS — первоначальное наполнение
 * и запасной вариант, если ключ ещё не сохранён.
 */
class SearchSettings
{
    public const GROUP = 'search';

    public const DEFAULTS = [
        'search_intent' => [
            'gearbox_at' => ['автомат', 'автомате', 'автоматом', 'автоматическая', 'акпп', 'ат', 'at', 'auto', 'automatic', 'робот', 'вариатор', 'cvt', 'dsg'],
            'gearbox_mt' => ['механика', 'механике', 'механикой', 'механическая', 'мкпп', 'мт', 'mt', 'manual', 'ручка'],
            'drive_4wd' => ['4wd', 'awd', '4x4', 'полный', 'полноприводный', 'полноприводная'],
            'fuel_electric' => ['электро', 'электромобиль', 'электрический', 'электричка', 'ev', 'electric'],
            'fuel_diesel' => ['дизель', 'дизельный', 'diesel'],
            'cheap' => ['дешево', 'дешевый', 'дешевые', 'недорого', 'недорогой', 'недорогие', 'бюджетный', 'бюджетно', 'cheap'],
            'price_max_words' => ['до', 'дешевле', 'максимум', 'under', 'max'],
            'price_min_words' => ['от', 'from', 'дороже'],
            'stopwords' => [
                'аренда', 'арендовать', 'прокат', 'авто', 'автомобиль', 'автомобили', 'машина', 'машину', 'машины', 'тачка',
                'снять', 'взять', 'в', 'во', 'на', 'с', 'со', 'и', 'для', 'по', 'без', 'крым', 'крыму', 'крыма',
                'rent', 'car', 'cars', 'the', 'коробка', 'коробке', 'кпп', 'привод', 'приводом', 'сутки', 'руб', 'рублей',
            ],
            'seat_words' => ['семиместный' => '7', 'семиместная' => '7', 'восьмиместный' => '8', 'девятиместный' => '9', 'многоместный' => '7'],
        ],

        'search_tuning' => [
            'weights' => ['name' => 3, 'brand' => 3, 'model' => 2.5, 'aliases' => 2.5, 'categories' => 1.6, 'features' => 1],
            'fuzzy' => ['min_length' => 4, 'max_distance_short' => 1, 'max_distance_long' => 2, 'layout_ratio' => 1.5],
            'limits' => ['per_page' => 24, 'suggest_cars' => 6, 'suggest_links' => 3, 'popular_brands' => 8, 'min_query_length' => 2],
        ],

        'search_ui' => [
            'header_trigger' => 'Поиск авто',
            'placeholder' => 'Марка, модель или «автомат до 3000»',
            'placeholder_catalog' => 'Найти в каталоге: марка, модель, «автомат до 3000»…',
            'placeholder_hero' => 'Например, «киа рио автомат» или «7 мест до 4000»',
            'hero_label' => 'Марка, модель или пожелание',
            'hero_submit' => 'Найти авто',
            'submit' => 'Найти',
            'group_recent' => 'Недавние запросы',
            'recent_clear' => 'Очистить',
            'group_popular' => 'Популярные марки',
            'group_links' => 'Разделы каталога',
            'group_cars' => 'Автомобили',
            'hint' => 'Можно писать по-русски, латиницей и даже в неправильной раскладке:',
            'examples' => ['хендай', 'solaris', 'автомат до 3000', '7 мест'],
            'understood' => 'Поняли:',
            'corrected' => 'Исправили раскладку:',
            'relaxed' => 'Точных совпадений нет — показываем похожие',
            'all_results' => 'Все результаты',
            'price_from' => 'от',
            'empty_title' => 'Ничего не нашли',
            'empty_text' => 'Попробуйте марку или модель: «рио», «камри», «кроссовер»',
            'page_title' => 'Поиск: :query | Car on Time',
            'page_description' => 'Результаты поиска автомобилей в аренду в Крыму по запросу «:query».',
            'page_found' => 'Найдено :count авто',
            'page_not_found' => 'Ничего не нашли',
            'page_query' => 'по запросу «:query»',
            'page_understood' => 'Учли в поиске:',
            'page_corrected' => 'Исправили раскладку: показываем результаты для «:query»',
            'page_relaxed' => 'Точных совпадений по всем словам нет — показываем похожие варианты.',
            'page_empty_title' => 'Попробуйте иначе',
            'page_empty_tips' => [
                'Марку или модель: «рио», «камри», «солярис»',
                'Класс или кузов: «кроссовер», «минивэн», «кабриолет»',
                'Пожелание: «автомат до 3000», «7 мест», «полный привод»',
            ],
            'page_catalog_button' => 'Смотреть весь каталог',
            'chip_at' => 'Автомат',
            'chip_mt' => 'Механика',
            'chip_price_max' => 'до :price ₽/сут',
            'chip_price_min' => 'от :price ₽/сут',
            'chip_seats' => 'от :seats мест',
            'chip_4wd' => 'Полный привод',
            'chip_electric' => 'Электро',
            'chip_diesel' => 'Дизель',
            'chip_cheap' => 'Сначала дешёвые',
        ],
    ];

    /** @var array<string, mixed> */
    private static array $memo = [];

    /** @return array<string, mixed> */
    public static function intent(): array
    {
        return self::group('search_intent');
    }

    /** @return array<string, float> */
    public static function weights(): array
    {
        return array_map('floatval', self::merge(self::DEFAULTS['search_tuning']['weights'], self::group('search_tuning')['weights'] ?? []));
    }

    /** @return array<string, float> */
    public static function fuzzy(): array
    {
        return array_map('floatval', self::merge(self::DEFAULTS['search_tuning']['fuzzy'], self::group('search_tuning')['fuzzy'] ?? []));
    }

    /** @return array<string, int> */
    public static function limits(): array
    {
        return array_map('intval', self::merge(self::DEFAULTS['search_tuning']['limits'], self::group('search_tuning')['limits'] ?? []));
    }

    /** @return array<string, mixed> */
    public static function ui(): array
    {
        return self::group('search_ui');
    }

    /** Текст интерфейса с подстановками: ui('page_found', ['count' => 5]). */
    public static function text(string $key, array $replace = []): string
    {
        $text = (string) (self::ui()[$key] ?? '');
        foreach ($replace as $name => $value) {
            $text = str_replace(':'.$name, (string) $value, $text);
        }

        return $text;
    }

    /**
     * Синонимы слов из названий: «solaris» → [«солярис», «соларис»].
     *
     * @return array<string, list<string>>
     */
    public static function termAliases(): array
    {
        return self::$memo['terms'] ??= Cache::remember('search:synonyms', 600, function () {
            $map = [];
            foreach (SearchSynonym::query()->where('is_active', true)->get() as $row) {
                foreach (TextNormalizer::tokens($row->term) as $term) {
                    $map[$term] = array_values(array_unique([...($map[$term] ?? []), ...(array) $row->synonyms]));
                }
            }

            return $map;
        });
    }

    /** Сброс после сохранения настроек или синонимов. */
    public static function flush(): void
    {
        self::$memo = [];
        Cache::forget('search:synonyms');
    }

    /** @return array<string, mixed> */
    private static function group(string $key): array
    {
        return self::$memo[$key] ??= self::merge(self::DEFAULTS[$key], (array) Setting::get($key, []));
    }

    /**
     * Поверхностное слияние: сохранённое значение ключа целиком заменяет значение по умолчанию.
     * Пустые строки и null не затирают дефолт — пустое поле в админке означает «как было».
     */
    private static function merge(array $defaults, array $stored): array
    {
        foreach ($stored as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $defaults[$key] = $value;
        }

        return $defaults;
    }
}
