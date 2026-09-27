<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\BodyType;
use App\Models\Brand;
use App\Models\Car;
use App\Models\CarClass;
use App\Models\City;
use App\Models\Page;
use App\Models\Promotion;
use App\Support\Seo\SeoSettings;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Карта сайта собирается из базы: всё опубликованное попадает сюда автоматически.
 * Кэш сбрасывается при любом изменении машин, статей, городов, страниц и справочников.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $xml = Cache::rememberForever('sitemap', fn () => view('sitemap', ['urls' => $this->urls()])->render());

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    private function urls(): Collection
    {
        $minCars = (int) (SeoSettings::indexing()['sitemap_min_cars'] ?? 1);
        $cars = Car::query()->published()->with(['media', 'brand', 'classes'])->orderBy('sort')->get();
        $latestCar = $cars->max('updated_at');
        $latestOf = fn (Collection $group) => $group->max('updated_at');

        $urls = collect([
            $this->url(route('home'), $latestCar, '1.0'),
            $this->url(route('catalog'), $latestCar, '0.9'),
            $this->url(route('quiz'), null, '0.6'),
            $this->url(route('faq'), DB::table('faqs')->max('updated_at'), '0.5'),
            $this->url(route('reviews'), DB::table('reviews')->where('is_published', true)->max('updated_at'), '0.5'),
        ]);

        foreach (CarClass::query()->orderBy('sort')->get() as $class) {
            $group = $cars->filter(fn (Car $c) => $c->classes->contains('id', $class->id));
            if ($group->count() >= $minCars) {
                $urls->push($this->url(route('klass', $class->slug), max($latestOf($group), $class->updated_at), '0.8'));
            }
        }
        foreach (BodyType::query()->orderBy('sort')->get() as $body) {
            $group = $cars->where('body_type_id', $body->id);
            if ($group->count() >= $minCars) {
                $urls->push($this->url(route('kuzov', $body->slug), max($latestOf($group), $body->updated_at), '0.8'));
            }
        }
        foreach (['at' => 'avtomat', 'mt' => 'mehanika'] as $gear => $slug) {
            $group = $cars->where('gearbox', $gear);
            if ($group->count() >= $minCars) {
                $urls->push($this->url(route('korobka', $slug), $latestOf($group), '0.7'));
            }
        }
        foreach (Brand::query()->orderBy('name')->get() as $brand) {
            $group = $cars->where('brand_id', $brand->id);
            if ($group->count() >= $minCars) {
                $urls->push($this->url(route('marka', $brand->slug), max($latestOf($group), $brand->updated_at), '0.6'));
            }
        }
        foreach (City::query()->published()->get() as $city) {
            $urls->push($this->url(route('city', $city->slug), max($latestCar, $city->updated_at), '0.8'));
        }

        foreach ($cars as $car) {
            $image = $car->coverUrl('', false);
            $urls->push($this->url(route('car.show', $car->slug), $car->updated_at, '0.7', $image ? [[
                'loc' => URL::to($image),
                'title' => $car->coverAlt(),
            ]] : []));
        }

        $articles = Article::query()->published()->get();
        if ($articles->isNotEmpty()) {
            $urls->push($this->url(route('articles'), $articles->max('updated_at'), '0.7'));
            foreach ($articles as $article) {
                $cover = $article->coverUrl('1200');
                $urls->push($this->url($article->url(), $article->updated_at, '0.6', $cover ? [[
                    'loc' => URL::to($cover),
                    'title' => $article->cover_alt ?: $article->title,
                ]] : []));
            }
        }

        $promotions = Promotion::query()->active()->get();
        if ($promotions->isNotEmpty()) {
            $urls->push($this->url(route('promotions'), $promotions->max('updated_at'), '0.6'));
            foreach ($promotions as $promotion) {
                $urls->push($this->url($promotion->url(), $promotion->updated_at, '0.5'));
            }
        }

        foreach (Page::query()->where('is_published', true)->where('slug', 'not like', 'gid-%')->get() as $page) {
            $urls->push($this->url(route('page', $page->slug), $page->updated_at, '0.4'));
        }

        return $urls->unique('loc')->values();
    }

    /**
     * @param  list<array{loc: string, title: string}>  $images
     * @return array<string, mixed>
     */
    private function url(string $loc, mixed $lastmod, string $priority, array $images = []): array
    {
        return [
            'loc' => $loc,
            'lastmod' => $lastmod ? Carbon::parse($lastmod)->toAtomString() : null,
            'priority' => $priority,
            'images' => $images,
        ];
    }
}
