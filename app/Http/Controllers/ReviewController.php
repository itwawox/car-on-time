<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\Review;
use App\Models\Setting;
use App\Services\Seo;
use App\Services\TelegramNotifier;
use App\Support\Seo\SeoSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(Request $request, Seo $seo): View
    {
        $reviews = Review::query()->published()->with('car:id,name,slug')->paginate(12);
        $summary = Review::summary();

        return view('reviews', [
            'reviews' => $reviews,
            'summary' => $summary,
            'car' => $request->filled('car') ? Car::query()->published()->where('slug', $request->string('car'))->first() : null,
            'cars' => Car::query()->published()->orderBy('name')->get(['id', 'name']),
            'title' => SeoSettings::paginate(SeoSettings::meta('reviews', 'title'), $reviews->currentPage()),
            'description' => SeoSettings::meta('reviews', 'description'),
            'noindex' => $request->has('car'),
            'jsonld' => array_filter([$seo->reviews($reviews->getCollection(), $summary)]),
            'crumbs' => [
                ['name' => 'Главная', 'url' => route('home')],
                ['name' => SeoSettings::meta('reviews', 'h1')],
            ],
        ]);
    }

    public function store(Request $request, TelegramNotifier $telegram): RedirectResponse
    {
        // Ловушка для ботов: поле скрыто от людей
        if ($request->filled('website')) {
            return back()->with('review_sent', true);
        }

        $data = $request->validate([
            'author' => ['required', 'string', 'min:2', 'max:80'],
            'phone' => ['nullable', 'string', 'max:32'],
            'city' => ['nullable', 'string', 'max:80'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['required', 'string', 'min:20', 'max:3000'],
            'car_id' => ['nullable', 'exists:cars,id'],
            'pd_consent' => ['accepted'],
        ], [
            'body.min' => 'Расскажите чуть подробнее — хотя бы пару предложений.',
            'pd_consent.accepted' => 'Нужно согласие на обработку данных и публикацию отзыва.',
        ], [
            'author' => 'имя', 'rating' => 'оценка', 'body' => 'отзыв',
        ]);

        $review = Review::query()->create([
            ...collect($data)->except('pd_consent')->all(),
            'consent_at' => now(),
            'body' => trim(strip_tags($data['body'])),
            'source' => 'site',
            'ip' => hash('sha256', (string) $request->ip()),
            'is_published' => false,
        ]);

        $telegram->reviewCreated($review);

        return back()->with('review_sent', true)->withFragment('review-form');
    }

    /** Текст из настроек с запасным значением. */
    public static function text(string $key, string $default): string
    {
        return (string) (Setting::get($key) ?: $default);
    }
}
