<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\Setting;
use App\Support\CarAlternatives;
use App\Support\Fleet;
use App\Support\Quiz;
use App\Support\QuizMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuizController extends Controller
{
    public function show(Request $request): View
    {
        return view('quiz', [
            'steps' => Quiz::steps(),
            'preset' => Quiz::answers($request->query()), // «Изменить ответы» со страницы результата
            'total' => count(Fleet::all()),
        ]);
    }

    public function result(Request $request): View
    {
        $answers = Quiz::answers($request->query());
        $match = QuizMatcher::match($answers);
        $ids = array_values(array_filter([$match['best']['id'] ?? null, $match['cheaper']['id'] ?? null, $match['comfort']['id'] ?? null]));
        $cars = Car::query()->published()->whereIn('id', $ids)->with(['brand', 'media', 'classes', 'prices', 'bodyType'])->get()->keyBy('id');

        $variants = [];
        foreach (['best' => 'Лучшее совпадение', 'cheaper' => 'Дешевле', 'comfort' => 'Комфортнее'] as $key => $label) {
            $row = $match[$key];
            if ($row && $cars->has($row['id'])) {
                $variants[] = ['key' => $key, 'label' => (string) (Setting::get('quiz_label_'.$key) ?: $label), 'car' => $cars[$row['id']], 'row' => $row];
            }
        }

        return view('quiz-result', [
            'answers' => $answers,
            'summary' => Quiz::summary($answers),
            'steps' => Quiz::steps(),
            'variants' => $variants,
            'count' => $match['count'],
            'relaxed' => $match['relaxed'],
            'catalogUrl' => route('catalog').(($f = $match['filters']) ? '?'.http_build_query($f) : ''),
            'editUrl' => route('quiz').'?'.http_build_query($answers),
        ]);
    }

    /** Живой счётчик «Подходит N машин» по уже данным ответам. */
    public function count(Request $request): JsonResponse
    {
        return response()->json(['count' => QuizMatcher::count(Quiz::answers($request->query()))]);
    }

    /**
     * «Похоже на то, что вы смотрели»: по просмотренным машинам (id из браузера) — частый кузов и коробка,
     * медианная цена; отдаём до 4 похожих машин, которые ещё не смотрели.
     */
    public function similar(Request $request): JsonResponse
    {
        $fleet = Fleet::all();
        $viewed = collect(explode(',', (string) $request->query('ids')))
            ->map(fn ($id) => (int) $id)->filter(fn ($id) => isset($fleet[$id]))->unique()->take(12)->values();
        if ($viewed->isEmpty()) {
            return response()->json(['cars' => []]);
        }

        $rows = $viewed->map(fn ($id) => $fleet[$id]);
        $prices = $rows->pluck('price')->filter()->sort()->values();
        $profile = [
            'body' => $rows->countBy('body')->sortDesc()->keys()->first(),
            'classes' => $rows->pluck('classes')->flatten()->countBy()->sortDesc()->keys()->take(1)->all(),
            'seats' => (int) round($rows->avg('seats')),
            'gearbox' => $rows->countBy('gearbox')->sortDesc()->keys()->first(),
            'price' => $prices->isNotEmpty() ? $prices[intdiv($prices->count(), 2)] : null,
        ];

        $cars = collect($fleet)
            ->reject(fn ($row) => $viewed->contains($row['id']) || ! $row['price'])
            ->map(fn ($row) => $row + ['score' => CarAlternatives::similarity($profile, $row)])
            ->sortBy([['score', 'desc'], ['sort', 'asc']])
            ->take(4)
            ->map(fn ($row) => [
                'id' => $row['id'], 'name' => $row['name'], 'url' => $row['url'], 'thumb' => $row['thumb'], 'price' => $row['price'],
                'meta' => implode(' · ', array_filter([$row['gearbox'] === 'mt' ? 'Механика' : 'Автомат', $row['seats'] ? $row['seats'].' мест' : null, $row['power'] ? $row['power'].' л.с.' : null])),
            ])
            ->values();

        return response()->json(['cars' => $cars]);
    }
}
