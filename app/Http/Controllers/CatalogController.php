<?php

namespace App\Http\Controllers;

use App\Models\BodyType;
use App\Models\Brand;
use App\Models\CarClass;
use App\Support\CatalogFilters;
use App\Support\CatalogListing;
use App\Support\Fleet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        // Старые ссылки ?class=slug ведут на страницу класса; массив class[] — это фильтры, их не трогаем
        if (is_string($request->query('class')) && $request->filled('class')) {
            return redirect()->route('klass', $request->query('class'));
        }

        return CatalogListing::render($request, ['type' => 'catalog']);
    }

    public function klass(Request $request, string $slug): View|RedirectResponse
    {
        $class = CarClass::query()->where('slug', $slug)->firstOrFail();

        return CatalogListing::render($request, [
            'type' => 'class',
            'name' => $class->name,
            'entity' => $class,
            'crumb' => $class->name,
            'filters' => ['class' => $class],
            'scope' => fn ($q) => $q->whereHas('classes', fn ($c) => $c->where('car_classes.id', $class->id)),
        ]);
    }

    public function kuzov(Request $request, string $slug): View|RedirectResponse
    {
        $body = BodyType::query()->where('slug', $slug)->firstOrFail();

        return CatalogListing::render($request, [
            'type' => 'body',
            'name' => $body->name,
            'entity' => $body,
            'crumb' => $body->name,
            'filters' => ['body' => $body],
            'scope' => fn ($q) => $q->where('body_type_id', $body->id),
        ]);
    }

    public function korobka(Request $request, string $slug): View|RedirectResponse
    {
        abort_unless(in_array($slug, ['avtomat', 'mehanika'], true), 404);
        $gear = $slug === 'mehanika' ? 'mt' : 'at';

        return CatalogListing::render($request, [
            'type' => 'gearbox_'.$gear,
            'crumb' => $gear === 'mt' ? 'Механика' : 'Автомат',
            'filters' => ['gearbox' => $gear],
            'scope' => fn ($q) => $q->where('gearbox', $gear),
        ]);
    }

    public function marka(Request $request, string $slug): View|RedirectResponse
    {
        $brand = Brand::query()->where('slug', $slug)->firstOrFail();

        return CatalogListing::render($request, [
            'type' => 'brand',
            'name' => $brand->name,
            'entity' => $brand,
            'crumb' => $brand->name,
            'filters' => ['brand' => $brand],
            'scope' => fn ($q) => $q->where('brand_id', $brand->id),
        ]);
    }

    /** Живой счётчик для панели фильтров: «Показать N авто». */
    public function count(Request $request): JsonResponse
    {
        $ids = CatalogFilters::ids(CatalogFilters::fromRequest($request));

        return response()->json(['count' => $ids === null ? count(Fleet::all()) : count($ids)]);
    }
}
