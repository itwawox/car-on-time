<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\BodyType;
use App\Models\Brand;
use App\Models\Car;
use App\Models\CarClass;
use App\Models\CarModel;
use App\Models\CarPrice;
use App\Models\City;
use App\Models\Faq;
use App\Models\Feature;
use App\Models\Location;
use App\Models\Page;
use App\Models\Promotion;
use App\Models\SearchSynonym;
use App\Models\Setting;
use App\Support\CarSeoLinks;
use App\Support\Fleet;
use App\Support\Places;
use App\Support\Search\CarSearch;
use App\Support\Search\QueryIntent;
use App\Support\Search\SearchSettings;
use App\Support\Search\SmartEngine;
use App\Support\Search\TermMatcher;
use App\Support\Seo\IndexNow;
use App\Support\Seo\SeoSettings;
use App\Support\WebpVariants;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.seo');
        Paginator::defaultSimpleView('vendor.pagination.seo');

        $this->app->make(EngineManager::class)->extend('smart', fn () => new SmartEngine);

        // Марки, классы и цены входят в документ машины — при их изменении индекс пересобирается
        $forget = function () {
            Cache::forget('sitemap');
            CarSeoLinks::forget();
            SearchSettings::flush();
            QueryIntent::flush();
            TermMatcher::flush();
            SmartEngine::forget('cars');
            CarSearch::forgetLinks();
            Fleet::forget();
        };
        foreach ([Car::class, Brand::class, CarClass::class, BodyType::class, Feature::class, CarPrice::class, SearchSynonym::class] as $model) {
            $model::saved($forget);
            $model::deleted($forget);
        }

        // Карта сайта, меню статей и IndexNow — при любом изменении публичного контента
        CarModel::saved(fn () => Fleet::forget());
        foreach (['saved', 'deleted'] as $event) {
            Promotion::$event(function (Promotion $promotion) {
                Cache::forget('promotions:has-active');
                Cache::forget('sitemap');
                if ($promotion->cover && $promotion->wasChanged('cover')) {
                    WebpVariants::make(Storage::disk('public')->path($promotion->cover), [600, 1200]);
                }
            });
        }
        Location::saved(fn () => Places::forget());
        Location::deleted(fn () => Places::forget());
        foreach ([City::class, Page::class, Article::class, Faq::class, CarModel::class] as $model) {
            $model::saved(function () {
                Cache::forget('sitemap');
                CarSeoLinks::forget();
            });
            $model::deleted(function () {
                Cache::forget('sitemap');
                CarSeoLinks::forget();
            });
        }
        Article::saved(function (Article $article) {
            Cache::forget('articles:has-published');
            if ($article->wasChanged('cover') || ($article->wasRecentlyCreated && $article->cover)) {
                WebpVariants::make(Storage::disk('public')->path($article->cover), [600, 1200]);
            }
            if ($article->is_published) {
                IndexNow::queue($article->url(), route('articles'));
            }
        });
        Article::deleted(fn () => Cache::forget('articles:has-published'));
        Car::saved(fn (Car $car) => $car->status === 'published' ? IndexNow::queue(route('car.show', $car->slug)) : null);
        City::saved(fn (City $city) => $city->is_published ? IndexNow::queue(route('city', $city->slug)) : null);
        Brand::saved(fn (Brand $brand) => IndexNow::queue(route('marka', $brand->slug)));
        Page::saved(fn (Page $page) => $page->is_published && ! str_starts_with($page->slug, 'gid-') ? IndexNow::queue(route('page', $page->slug)) : null);

        // Настройки умного поиска из Filament применяются сразу
        Setting::saved(function (Setting $setting) use ($forget) {
            if ($setting->group === SearchSettings::GROUP) {
                $forget();
            }
            if ($setting->group === SeoSettings::GROUP) {
                SeoSettings::flush();
                Cache::forget('sitemap');
            }
        });
    }
}
