<?php

namespace App\Support;

use App\Models\Setting;

/**
 * «Для какой поездки?» на главной: сценарии вместо списка классов и кузовов.
 * Каждый сценарий — набор фильтров каталога (CatalogFilters), поэтому число машин и цена «от»
 * совпадают с тем, что человек увидит, перейдя по ссылке. Фото — обложка первой подходящей машины.
 */
class HomeScenarios
{
    /** ключ => [заголовок, подпись, иконка, фильтры каталога] */
    public const SCENARIOS = [
        'family' => ['Для семьи', 'Кроссоверы, универсалы и минивэны', 'users', ['body' => ['krossover', 'universal', 'miniven', 'liftbek']]],
        'sea' => ['Город и море', 'Компактные на автомате', 'city', ['kp' => 'at', 'body' => ['sedan', 'hetchbek']]],
        'mountains' => ['В горы', 'Полный привод', 'mountain', ['awd' => true]],
        'business' => ['Деловая поездка', 'Бизнес-класс', 'briefcase', ['class' => ['biznes']]],
        'fun' => ['Кабриолеты и купе', 'Для впечатлений на ЮБК', 'star', ['body' => ['kabriolet', 'kupe']]],
        'economy' => ['Эконом', 'Самые доступные машины', 'wallet', ['class' => ['ekonom']]],
    ];

    /** @return list<array{key: string, title: string, text: string, icon: string, count: int, price: ?int, thumb: ?string, cover_id: ?int, url: string}> */
    public static function all(): array
    {
        $fleet = Fleet::all();
        $out = [];
        foreach (self::SCENARIOS as $key => [$title, $text, $icon, $filters]) {
            $ids = CatalogFilters::ids($filters) ?? [];
            if (! $ids) {
                continue;
            }
            $rows = array_values(array_filter(array_map(fn ($id) => $fleet[$id] ?? null, $ids)));
            usort($rows, fn ($a, $b) => $a['sort'] <=> $b['sort']);
            $prices = array_filter(array_column($rows, 'price'));
            $cover = collect($rows)->first(fn ($r) => $r['thumb']);

            $query = $filters;
            if (! empty($query['awd'])) {
                $query['awd'] = 1;
            }

            $out[] = [
                'key' => $key,
                'title' => (string) (Setting::get('scenario_'.$key.'_title') ?: $title),
                'text' => (string) (Setting::get('scenario_'.$key.'_text') ?: $text),
                'icon' => $icon,
                'count' => count($rows),
                'price' => $prices ? min($prices) : null,
                'thumb' => $cover['thumb'] ?? null,
                'cover_id' => $cover['id'] ?? null,
                'url' => route('catalog').'?'.self::query($query),
            ];
        }

        return $out;
    }

    /**
     * Вкладки «Популярные машины»: id машин по порядку каталога.
     *
     * @return list<array{key: string, label: string, ids: list<int>, url: string, total: int}>
     */
    public static function popularTabs(int $limit = 8): array
    {
        $tabs = [
            'all' => ['Все', null, route('catalog')],
            'ekonom' => ['Эконом', ['class' => ['ekonom']], route('klass', 'ekonom')],
            'krossover' => ['Кроссоверы', ['body' => ['krossover']], route('kuzov', 'krossover')],
            'biznes' => ['Бизнес', ['class' => ['biznes']], route('klass', 'biznes')],
            'seats' => ['7+ мест', ['seats' => 7], route('catalog').'?seats=7'],
        ];
        $fleet = Fleet::all();
        uasort($fleet, fn ($a, $b) => $a['sort'] <=> $b['sort']);

        $out = [];
        foreach ($tabs as $key => [$label, $filters, $url]) {
            $ids = $filters ? (CatalogFilters::ids($filters) ?? []) : array_keys($fleet);
            $ordered = array_values(array_filter(array_keys($fleet), fn ($id) => in_array($id, $ids, true)));
            if (! $ordered) {
                continue;
            }
            $out[] = ['key' => $key, 'label' => $label, 'ids' => array_slice($ordered, 0, $key === 'all' ? $limit : 4), 'url' => $url, 'total' => count($ordered)];
        }

        return $out;
    }

    /** @return list<array{label: string, url: string}> подсказки «Часто ищут» под формой */
    public static function hints(): array
    {
        $custom = Setting::get('hero_hints');
        if (is_array($custom) && $custom) {
            return array_values(array_filter(array_map(fn ($h) => [
                'label' => trim((string) ($h['label'] ?? '')),
                'url' => route('catalog').(filled($h['query'] ?? null) ? '?'.ltrim((string) $h['query'], '?') : ''),
            ], $custom), fn ($h) => $h['label'] !== ''));
        }
        $budget = Quiz::budgets()[1];

        return [
            ['label' => 'Автомат до '.Quiz::money($budget), 'url' => route('catalog').'?'.self::query(['kp' => 'at', 'price_max' => $budget])],
            ['label' => '7+ мест', 'url' => route('catalog').'?seats=7'],
            ['label' => 'Полный привод', 'url' => route('catalog').'?awd=1'],
            ['label' => 'Кабриолеты', 'url' => route('kuzov', 'kabriolet')],
            ['label' => 'Бизнес-класс', 'url' => route('klass', 'biznes')],
        ];
    }

    /** body[]=a&body[]=b вместо body[0]=a — короче и понятнее в адресе. */
    public static function query(array $params): string
    {
        return (string) preg_replace('/%5B\d+%5D/', '%5B%5D', http_build_query($params));
    }
}
