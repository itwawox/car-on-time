<?php

namespace App\Support\Search;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;

/**
 * Драйвер Scout без внешнего сервера: индекс живёт в кэше Laravel,
 * поиск идёт в PHP с учётом раскладки, транслита и опечаток.
 *
 * Для каталога на сотни–тысячи позиций это быстрее сетевого запроса к движку.
 * Если понадобится Meilisearch — меняется только SCOUT_DRIVER.
 */
class SmartEngine extends Engine
{
    /** Поля документа, по которым идёт текстовый поиск, и чей вес из настроек к ним применяется. */
    private const TEXT_FIELDS = [
        'name' => 'name',
        'brand' => 'brand',
        'brand_aliases' => 'brand',
        'model' => 'model',
        'aliases' => 'aliases',
        'categories' => 'categories',
        'features' => 'features',
    ];

    public function update($models)
    {
        self::forget($models->first()?->searchableAs() ?? 'cars');
    }

    public function delete($models)
    {
        self::forget($models->first()?->searchableAs() ?? 'cars');
    }

    public function flush($model)
    {
        self::forget($model->searchableAs());
    }

    public function createIndex($name, array $options = [])
    {
        //
    }

    public function deleteIndex($name)
    {
        self::forget($name);
    }

    public static function forget(string $index): void
    {
        Cache::forget("search:{$index}:docs");
    }

    public function search(Builder $builder)
    {
        $result = $this->run($builder);
        $result['results'] = array_slice($result['results'], 0, $builder->limit ?: null);

        return $result;
    }

    public function paginate(Builder $builder, $perPage, $page)
    {
        $result = $this->run($builder);
        $result['results'] = array_slice($result['results'], ($page - 1) * $perPage, $perPage);

        return $result;
    }

    public function mapIds($results)
    {
        return collect($results['results'])->pluck('id')->values();
    }

    public function map(Builder $builder, $results, $model)
    {
        $ids = collect($results['results'])->pluck('id')->all();
        if ($ids === []) {
            return $model->newCollection();
        }

        $positions = array_flip($ids);

        return $model->getScoutModelsByIds($builder, $ids)
            ->sortBy(fn ($m) => $positions[$m->getScoutKey()] ?? PHP_INT_MAX)
            ->values();
    }

    public function lazyMap(Builder $builder, $results, $model)
    {
        return LazyCollection::make($this->map($builder, $results, $model)->all());
    }

    public function getTotalCount($results)
    {
        return $results['total'];
    }

    /**
     * @return array{results: list<array{id: int|string, score: float}>, total: int, meta: array<string, mixed>}
     */
    private function run(Builder $builder): array
    {
        $docs = $this->documents($builder);
        $query = trim((string) $builder->query);

        $primary = $this->evaluate($docs, $query, $builder);

        // Запрос, набранный не в той раскладке: «ьфяв» → «mazd», «htyj» → «рено»
        $swapped = TextNormalizer::swapLayout($query);
        if ($query !== '' && $swapped !== $query) {
            $alt = $this->evaluate($docs, $swapped, $builder);
            if ($alt['total'] > 0 && ($primary['total'] === 0 || $alt['top'] > $primary['top'] * SearchSettings::fuzzy()['layout_ratio'])) {
                $alt['meta']['corrected'] = $swapped;
                $primary = $alt;
            }
        }

        // Ничего не нашли по всем словам — показываем совпадения хотя бы по части
        if ($primary['total'] === 0 && $query !== '') {
            $relaxed = $this->evaluate($docs, $query, $builder, relaxed: true);
            if ($relaxed['total'] > 0) {
                $relaxed['meta']['relaxed'] = true;
                $primary = $relaxed;
            }
        }

        unset($primary['top']);

        return $primary;
    }

    /**
     * @param  list<array<string, mixed>>  $docs
     * @return array{results: list<array{id: int|string, score: float}>, total: int, top: float, meta: array<string, mixed>}
     */
    private function evaluate(array $docs, string $query, Builder $builder, bool $relaxed = false): array
    {
        $intent = QueryIntent::parse($query);
        $keys = array_values(array_unique(array_filter(array_map(TextNormalizer::key(...), $intent->terms))));
        $hits = [];

        // Сходство считаем один раз на уникальное слово индекса, а не на каждый документ
        $vocabulary = $this->vocabulary($docs);
        $candidates = [];
        foreach ($keys as $key) {
            $candidates[$key] = [];
            foreach ($vocabulary as $term) {
                $sim = TermMatcher::similarity($key, $term);
                if ($sim > 0) {
                    $candidates[$key][$term] = $sim;
                }
            }
        }

        foreach ($docs as $doc) {
            if (! $this->passesFilters($doc['attributes'], $intent, $builder)) {
                continue;
            }

            $score = 0.0;
            $matched = 0;
            foreach ($keys as $key) {
                $best = 0.0;
                foreach ($candidates[$key] as $term => $sim) {
                    if (isset($doc['terms'][$term])) {
                        $best = max($best, $sim * $doc['terms'][$term]);
                    }
                }
                if ($best > 0) {
                    $matched++;
                    $score += $best;
                }
            }

            if ($keys !== [] && ($relaxed ? $matched === 0 : $matched < count($keys))) {
                continue;
            }

            $hits[] = [
                'id' => $doc['id'],
                'score' => round($score + $matched * 10, 4),
                'price' => $doc['attributes']['price_from'] ?? PHP_INT_MAX,
                'sort' => $doc['attributes']['sort'] ?? 0,
            ];
        }

        usort($hits, function ($a, $b) use ($intent) {
            if ($intent->cheapFirst) {
                return [$a['price'], -$a['score']] <=> [$b['price'], -$b['score']];
            }

            return [-$a['score'], $a['sort'], $a['price']] <=> [-$b['score'], $b['sort'], $b['price']];
        });

        return [
            'results' => array_map(fn ($h) => ['id' => $h['id'], 'score' => $h['score']], $hits),
            'total' => count($hits),
            'top' => $hits[0]['score'] ?? 0.0,
            'meta' => [
                'query' => $query,
                'terms' => $intent->terms,
                'chips' => $intent->chips(),
                'corrected' => null,
                'relaxed' => false,
            ],
        ];
    }

    /**
     * @param  list<array{terms: array<string, float>}>  $docs
     * @return list<string>
     */
    private function vocabulary(array $docs): array
    {
        $terms = [];
        foreach ($docs as $doc) {
            foreach ($doc['terms'] as $term => $weight) {
                $terms[(string) $term] = true;
            }
        }

        return array_map('strval', array_keys($terms));
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function passesFilters(array $attrs, QueryIntent $intent, Builder $builder): bool
    {
        $price = $attrs['price_from'] ?? null;

        if ($intent->gearbox && ($attrs['gearbox'] ?? null) !== $intent->gearbox) {
            return false;
        }
        if ($intent->priceMax && (! $price || $price > $intent->priceMax)) {
            return false;
        }
        if ($intent->priceMin && (! $price || $price < $intent->priceMin)) {
            return false;
        }
        if ($intent->seatsMin && (int) ($attrs['seats'] ?? 0) < $intent->seatsMin) {
            return false;
        }
        if ($intent->drivetrain && ($attrs['drivetrain'] ?? null) !== $intent->drivetrain) {
            return false;
        }
        if ($intent->fuel && ($attrs['fuel'] ?? null) !== $intent->fuel) {
            return false;
        }

        foreach ($builder->wheres as $where) {
            $field = $where['field'] ?? null;
            if ($field === null || $field === '__soft_deleted') {
                continue;
            }
            $value = $attrs[$field] ?? null;
            $ok = is_array($value) ? in_array($where['value'], $value, false) : $value == $where['value'];
            if (! $ok) {
                return false;
            }
        }

        foreach ($builder->whereIns as $field => $values) {
            $value = $attrs[$field] ?? null;
            $ok = is_array($value) ? array_intersect($value, $values) !== [] : in_array($value, $values, false);
            if (! $ok) {
                return false;
            }
        }

        return true;
    }

    /**
     * Индекс: для каждой записи — фонетические ключи с весами и атрибуты для фильтров.
     *
     * @return list<array{id: int|string, terms: array<string, float>, attributes: array<string, mixed>}>
     */
    private function documents(Builder $builder): array
    {
        $model = $builder->model;
        $index = $model->searchableAs();

        return Cache::remember("search:{$index}:docs", 600, function () use ($model) {
            $docs = [];
            $weights = SearchSettings::weights();

            $model::makeAllSearchableQuery()->chunk(500, function ($chunk) use (&$docs, $weights) {
                foreach ($chunk as $item) {
                    if (! $item->shouldBeSearchable()) {
                        continue;
                    }

                    $data = $item->toSearchableArray();
                    $fields = [];
                    foreach (self::TEXT_FIELDS as $field => $weightKey) {
                        $value = $data[$field] ?? null;
                        $fields[] = [is_array($value) ? implode(' ', $value) : $value, $weights[$weightKey] ?? 1.0];
                    }

                    $docs[] = [
                        'id' => $item->getScoutKey(),
                        'terms' => TermMatcher::buildTerms($fields),
                        'attributes' => array_diff_key($data, self::TEXT_FIELDS),
                    ];
                }
            });

            return $docs;
        });
    }
}
