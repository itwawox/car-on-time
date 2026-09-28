<?php

namespace App\Http\Controllers;

use App\Models\BodyType;
use App\Models\CarClass;
use App\Support\Search\CarSearch;
use App\Support\Search\SearchLogger;
use App\Support\Search\SearchSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function index(Request $request, CarSearch $search): View|RedirectResponse
    {
        $query = Str::limit(trim((string) $request->query('q', '')), 100, '');

        // Форма на главной без текста: ведём на SEO-страницы каталога
        if ($query === '') {
            if ($class = $request->query('class')) {
                return redirect()->route('klass', $class);
            }
            if (in_array($request->query('kp'), ['at', 'mt'], true)) {
                return redirect()->route('korobka', $request->query('kp') === 'at' ? 'avtomat' : 'mehanika');
            }

            return redirect()->route('catalog');
        }

        $page = max(1, (int) $request->query('page', 1));
        $filters = array_filter([
            'class' => CarClass::query()->where('slug', $request->query('class'))->value('slug'),
            'kp' => in_array($request->query('kp'), ['at', 'mt'], true) ? $request->query('kp') : null,
        ]);
        ['cars' => $cars, 'meta' => $meta] = $search->page($query, $page, $filters);

        if ($page === 1) {
            SearchLogger::record($query, $cars->total(), $request->session()->getId());
        }

        return view('search', [
            'query' => $query,
            'cars' => $cars,
            'meta' => $meta,
            'filters' => $filters,
            'classes' => CarClass::query()->withCars()->orderBy('sort')->get(),
            'bodies' => BodyType::query()->withCars()->orderBy('sort')->get(),
            'popular' => $cars->total() === 0 ? $search->popularBrands() : [],
        ]);
    }

    public function suggest(Request $request, CarSearch $search): JsonResponse
    {
        $query = Str::limit(trim((string) $request->query('q', '')), 100, '');

        if (mb_strlen($query) < SearchSettings::limits()['min_query_length']) {
            return response()->json(['query' => $query, 'popular' => $search->popularBrands()]);
        }

        $data = $search->suggest($query);

        // Посетитель перестал печатать — запрос считается «законченным» и попадает в журнал
        if ($request->boolean('final')) {
            SearchLogger::record($query, $data['total'], $request->session()->getId());

            return response()->json($data);
        }

        return response()->json($data)->header('Cache-Control', 'public, max-age=60');
    }
}
