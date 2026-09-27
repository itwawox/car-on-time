<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\Extra;
use App\Models\Faq;
use App\Models\Location;
use App\Services\Seo;
use App\Support\CarAlternatives;
use Illuminate\Http\Response;

class CarController extends Controller
{
    public function show(string $slug): Response
    {
        $car = Car::query()->published()->where('slug', $slug)->with(['brand', 'media', 'classes', 'features', 'prices.season', 'bodyType', 'carModel'])->firstOrFail();

        $classIds = $car->classes->pluck('id');
        $sameClass = Car::query()->published()
            ->where('id', '!=', $car->id)
            ->whereHas('classes', fn ($q) => $q->whereIn('car_classes.id', $classIds))
            ->with(['brand', 'media', 'prices', 'classes'])
            ->orderBy('sort')
            ->limit(8)
            ->get();

        $alternatives = CarAlternatives::for($car);

        $seo = app(Seo::class);
        $crumbs = [
            ['name' => 'Главная', 'url' => route('home')],
            ['name' => 'Каталог', 'url' => route('catalog')],
            ['name' => $car->name],
        ];

        return response()->view('car', [
            'car' => $car,
            'locations' => Location::query()->where('is_active', true)->orderBy('sort')->get(),
            'sameClass' => $sameClass,
            'alternatives' => $alternatives,
            'extrasList' => Extra::query()->active()->get(),
            'faqs' => $carFaqs = Faq::query()->published()->where('show_on_car', true)->get(),
            'carReviews' => $car->reviews()->published()->limit(10)->get(),
            'crumbs' => $crumbs,
            'jsonld' => [
                $seo->vehicle($car),
                $seo->breadcrumbs($crumbs),
                $seo->faq($carFaqs),
            ],
        ])->setLastModified($car->updated_at);
    }
}
