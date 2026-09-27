<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Support\CatalogListing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CityController extends Controller
{
    public function __invoke(Request $request, string $slug): View|RedirectResponse
    {
        $city = City::query()->published()->where('slug', $slug)->firstOrFail();

        return CatalogListing::render($request, [
            'type' => 'city',
            'name' => $city->name,
            'entity' => $city,
            'crumb' => $city->name,
            'filters' => ['city' => $city],
        ]);
    }
}
