<?php

namespace App\Http\Controllers;

use App\Models\BodyType;
use App\Models\Car;
use App\Models\CarClass;
use App\Models\City;
use App\Models\Faq;
use App\Models\Location;
use App\Models\Review;
use App\Services\Seo;
use App\Support\HeroStage;
use App\Support\HomeScenarios;
use App\Support\Places;
use App\Support\Seo\SeoSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Seo $seo): View
    {
        $faqs = Faq::query()->published()->where('is_featured', true)->limit(8)->get();
        $scenarios = HomeScenarios::all();

        return view('home', [
            'classes' => CarClass::query()->withCars()->orderBy('sort')->get(),
            'bodies' => BodyType::query()->withCars()->orderBy('sort')->get(),
            'scenarios' => $scenarios,
            'stage' => HeroStage::slides($scenarios),
            'popular' => $this->popular(),
            'hints' => HomeScenarios::hints(),
            'cityDelivery' => $this->cityDelivery(),
            'faqGroups' => $this->faqGroups(),
            'locations' => Location::query()->where('is_active', true)->orderBy('sort')->get(),
            'faqs' => $faqs,
            'reviews' => Review::query()->published()->with('car:id,name,slug')->limit(3)->get(),
            'reviewsSummary' => Review::summary(),
            'count' => Car::query()->published()->count(),
            'jsonld' => array_filter([$seo->website(), $seo->faq($faqs)]),
            'title' => SeoSettings::meta('home', 'title', $vars = [
                'count' => Car::query()->published()->count(),
                'price' => DB::table('car_prices')->whereIn('car_id', Car::query()->published()->select('id'))->min('price'),
            ]),
            'description' => SeoSettings::meta('home', 'description', $vars),
        ]);
    }

    /** Вкладки «Популярные машины» с моделями машин для карточек (один запрос на все вкладки). */
    private function popular(): array
    {
        $tabs = HomeScenarios::popularTabs();
        $ids = array_unique(array_merge(...array_column($tabs, 'ids') ?: [[]]));
        $cars = Car::query()->published()->whereIn('id', $ids)->with(['brand', 'media', 'classes', 'prices'])->get()->keyBy('id');

        return array_map(fn ($tab) => $tab + ['cars' => collect($tab['ids'])->map(fn ($id) => $cars[$id] ?? null)->filter()->values()], $tabs);
    }

    /** «Доставка от X ₽» для плиток городов — по точкам выдачи этого города. @return array<int, string> */
    private function cityDelivery(): array
    {
        $places = collect(Places::all());
        $out = [];
        foreach (City::query()->published()->where('show_on_home', true)->get() as $city) {
            $group = $places->filter(fn ($p) => mb_strtolower($p['group']) === mb_strtolower($city->name));
            if ($group->isEmpty()) {
                continue;
            }
            $out[$city->id] = $group->contains(fn ($p) => $p['price'] === 'бесплатно') ? 'доставка бесплатно от 3 суток' : str_replace('доставка ', 'доставка от ', (string) $group->sortBy(fn ($p) => (int) preg_replace('/\D/', '', $p['price']))->first()['price']);
        }

        return $out;
    }

    /** Разделы FAQ со счётчиками для левой колонки блока «Частые вопросы». @return list<array{key: string, label: string, count: int}> */
    private function faqGroups(): array
    {
        $counts = Faq::query()->where('is_published', true)->selectRaw('`group` as g, count(*) as c')->groupBy('g')->pluck('c', 'g');

        return collect(Faq::GROUPS)->keys()
            ->filter(fn ($key) => ($counts[$key] ?? 0) > 0)
            ->map(fn ($key) => ['key' => $key, 'label' => Faq::groupLabel($key), 'count' => (int) $counts[$key]])
            ->values()->all();
    }
}
