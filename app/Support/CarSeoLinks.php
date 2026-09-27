<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Car;
use App\Models\CarClass;
use App\Models\City;
use App\Support\Seo\SeoSettings;
use Illuminate\Support\Facades\Cache;

/**
 * SEO-блок внизу карточки машины: связный текст с 4–7 контекстными ссылками
 * на главную, класс, кузов, коробку, марку, города и статью.
 *
 * Шаблоны предложений — в «SEO → Настройки SEO → Описания машин» (links_*).
 * Плейсхолдер {link:key} или {link:key|свой анкор}. Предложение, для которого нет
 * опубликованной страницы, пропускается целиком — битых и пустых ссылок не бывает.
 */
class CarSeoLinks
{
    private const ORDER = ['links_intro', 'links_catalog', 'links_body', 'links_gearbox', 'links_brand', 'links_cities', 'links_article'];

    /**
     * @return array{heading: string, html: string, links: int}
     */
    public static function build(Car $car): array
    {
        $car->loadMissing(['brand', 'bodyType', 'classes']);
        $links = self::links($car);
        $sentences = [];
        $used = [];
        $count = 0;

        foreach (self::ORDER as $key) {
            $template = SeoSettings::pick($key, $car->id);
            if (! $template) {
                continue;
            }

            preg_match_all('/\{link:(\w+)(?:\|([^}]*))?\}/u', $template, $m, PREG_SET_ORDER);
            // Предложение без доступных ссылок или с уже использованной ссылкой — пропускаем
            if ($m === [] || collect($m)->contains(fn ($x) => ! isset($links[$x[1]]) || isset($used[$x[1]]))) {
                continue;
            }

            $html = e(SeoSettings::fill(preg_replace('/\{link:[^}]+\}/u', "\u{1}", $template), ['name' => $car->name]));
            foreach ($m as $match) {
                $html = preg_replace('/\x{1}/u', self::render($links[$match[1]], $match[2] ?? null), $html, 1);
                $used[$match[1]] = true;
                $count += is_array($links[$match[1]][0] ?? null) ? count($links[$match[1]]) : 1;
            }
            $sentences[] = $html;
        }

        return [
            'heading' => 'Аренда '.$car->name.' в Крыму',
            'html' => $sentences ? '<p>'.implode(' ', $sentences).'</p>' : '',
            'links' => $count,
        ];
    }

    /**
     * Доступные ссылки: ключ → [url, анкор по умолчанию] или список таких пар (города).
     *
     * @return array<string, mixed>
     */
    private static function links(Car $car): array
    {
        $stats = self::stats();
        $minCars = max(1, (int) (SeoSettings::indexing()['sitemap_min_cars'] ?? 1));
        $links = ['home' => [route('home'), 'аренда авто в Крыму']];

        if ($class = self::mainClass($car)) {
            $links['class'] = [route('klass', $class->slug), mb_strtolower($class->name)];
        }
        if ($car->bodyType && ($stats['bodies'][$car->body_type_id] ?? 0) >= $minCars) {
            $links['body'] = [route('kuzov', $car->bodyType->slug), mb_strtolower($car->bodyType->name)];
        }
        $links['gearbox'] = $car->gearbox === 'mt'
            ? [route('korobka', 'mehanika'), 'авто на механике']
            : [route('korobka', 'avtomat'), 'авто на автомате'];
        if ($car->brand && ($stats['brands'][$car->brand_id] ?? 0) >= 2) {
            $links['brand'] = [route('marka', $car->brand->slug), 'аренда '.$car->brand->name.' в Крыму'];
        }

        $cities = self::cities($car);
        if ($cities !== []) {
            $links['cities'] = array_map(fn (array $c) => [route('city', $c['slug']), mb_lcfirst($c['h1'] ?: 'аренда авто: '.$c['name'])], $cities);
        }

        if ($article = self::article($car)) {
            $links['article'] = [route('article', $article['slug']), $article['title']];
        }

        return $links;
    }

    /**
     * @param  array{0: string, 1: string}|list<array{0: string, 1: string}>  $link
     */
    private static function render(array $link, ?string $anchor): string
    {
        if (is_array($link[0] ?? null)) {
            $items = array_map(fn ($l) => '<a href="'.e($l[0]).'">'.e($l[1]).'</a>', $link);
            $last = array_pop($items);

            return $items ? implode(', ', $items).' и '.$last : $last;
        }

        return '<a href="'.e($link[0]).'">'.e(filled($anchor) ? $anchor : $link[1]).'</a>';
    }

    /**
     * Аэропорт всегда и ещё один город по кругу — у соседних машин разные.
     * В кэше — массивы: объекты моделей из кэша не восстанавливаются (cache.serializable_classes).
     *
     * @return list<array{slug: string, name: string, h1: ?string}>
     */
    private static function cities(Car $car): array
    {
        $all = Cache::remember('car-links:cities', 600, fn () => City::query()->published()->get(['slug', 'name', 'h1'])->toArray());
        $airport = collect($all)->first(fn ($c) => str_contains($c['slug'], 'aeroport'));
        $others = array_values(array_filter($all, fn ($c) => $c['slug'] !== ($airport['slug'] ?? null)));

        $picked = array_filter([$airport]);
        if ($others !== []) {
            $picked[] = $others[crc32('cities|'.$car->id) % count($others)];
        }

        return array_values($picked);
    }

    /**
     * Статья по теме: горы — для полного привода, ЮБК — для кабриолетов, семья — для 6+ мест, иначе аэропорт.
     *
     * @return array{slug: string, title: string, category: ?string}|null
     */
    private static function article(Car $car): ?array
    {
        $articles = Cache::remember('car-links:articles', 600, fn () => Article::query()->published()->get(['slug', 'title', 'category'])->toArray());
        if ($articles === []) {
            return null;
        }

        $topic = match (true) {
            $car->drivetrain === '4wd' => '/гор|ай-петри|бездорож|внедорож/iu',
            $car->bodyType?->slug === 'kabriolet' => '/юбк|кабриолет|побереж|ялт/iu',
            $car->seats >= 6 => '/сем|дет|компани/iu',
            default => '/аэропорт|симферопол/iu',
        };

        return collect($articles)->first(fn ($a) => preg_match($topic, $a['title'].' '.$a['category']))
            ?? $articles[crc32('article|'.$car->id) % count($articles)];
    }

    private static function mainClass(Car $car): ?CarClass
    {
        foreach (['biznes', 'ekonom', 'srednij'] as $slug) {
            if ($class = $car->classes->firstWhere('slug', $slug)) {
                return $class;
            }
        }

        return $car->classes->first();
    }

    /**
     * @return array{brands: array<int, int>, bodies: array<int, int>}
     */
    private static function stats(): array
    {
        return Cache::remember('car-links:stats', 600, fn () => [
            'brands' => Car::query()->published()->selectRaw('brand_id, count(*) c')->groupBy('brand_id')->pluck('c', 'brand_id')->all(),
            'bodies' => Car::query()->published()->selectRaw('body_type_id, count(*) c')->groupBy('body_type_id')->pluck('c', 'body_type_id')->all(),
        ]);
    }

    public static function forget(): void
    {
        Cache::forget('car-links:cities');
        Cache::forget('car-links:articles');
        Cache::forget('car-links:stats');
    }
}
