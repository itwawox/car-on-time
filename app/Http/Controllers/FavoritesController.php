<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Избранное: список хранится в браузере (localStorage), страница получает его в ?ids=. Закрыта от индексации.
 */
class FavoritesController extends Controller
{
    public const LIMIT = 30;

    public function __invoke(Request $request): View
    {
        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(fn ($id) => (int) $id)->filter()->unique()->take(self::LIMIT)->values();

        $cars = Car::query()->published()->whereIn('id', $ids)
            ->with(['brand', 'media', 'prices', 'classes'])->get()
            ->sortBy(fn (Car $car) => $ids->search($car->id))->values();

        $title = Setting::get('favorites_title') ?: 'Избранное';

        return view('favorites', [
            'cars' => $cars,
            'title' => $title.' | '.Setting::get('brand_name', 'Car on Time'),
            'heading' => $title,
            'noindex' => true,
            'crumbs' => [['name' => 'Главная', 'url' => route('home')], ['name' => $title]],
        ]);
    }
}
