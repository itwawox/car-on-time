<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Car;
use App\Services\Seo;
use App\Support\CatalogPager;
use App\Support\Seo\SeoSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $query = Article::query()->published();
        if ($category = $request->query('rubrika')) {
            $query->where('category', $category);
        }

        $articles = CatalogPager::paginate($query, $request, route('articles'), 12);
        if ($articles instanceof RedirectResponse) {
            return $articles;
        }

        $page = $articles->currentPage();
        $crumbs = [['name' => 'Главная', 'url' => route('home')], ['name' => 'Статьи']];

        return view('articles.index', [
            'articles' => $articles,
            'categories' => Article::query()->published()->reorder()->whereNotNull('category')->distinct()->pluck('category'),
            'title' => SeoSettings::paginate(SeoSettings::meta('articles', 'title'), $page),
            'h1' => SeoSettings::paginate(SeoSettings::meta('articles', 'h1'), $page),
            'description' => SeoSettings::meta('articles', 'description'),
            'intro' => SeoSettings::meta('articles', 'intro'),
            'crumbs' => $crumbs,
            // Рубрики через параметр — рабочие, но не отдельные страницы для индекса
            'noindex' => $request->query('rubrika') !== null,
            'canonical' => $articles->url($page),
            'jsonld' => [app(Seo::class)->breadcrumbs($crumbs)],
        ]);
    }

    public function show(string $slug): Response
    {
        $article = Article::query()->published()->where('slug', $slug)->firstOrFail();
        $seo = app(Seo::class);

        $crumbs = [
            ['name' => 'Главная', 'url' => route('home')],
            ['name' => 'Статьи', 'url' => route('articles')],
            ['name' => $article->title],
        ];

        $cars = collect();
        if ($article->cars_query) {
            $ids = array_slice(array_column(Car::search($article->cars_query)->raw()['results'], 'id'), 0, 6);
            $cars = Car::query()->with(['brand', 'classes', 'prices', 'media'])->whereIn('id', $ids)->get()
                ->sortBy(fn ($car) => array_search($car->id, $ids))->values();
        }

        $faqs = collect((array) $article->faq)->filter(fn ($f) => filled($f['question'] ?? null) && filled($f['answer'] ?? null))
            ->map(fn ($f) => (object) ['question' => $f['question'], 'answer' => $f['answer']])->values();

        return response()->view('articles.show', [
            'article' => $article,
            'body' => $article->contentWithToc(),
            'cars' => $cars,
            'faqs' => $faqs,
            'related' => Article::query()->published()->whereKeyNot($article->id)
                ->when($article->category, fn ($q) => $q->orderByRaw('category = ? desc', [$article->category]))
                ->limit(3)->get(),
            'title' => $article->seo_title ?: SeoSettings::meta('article', 'title', ['name' => $article->title]),
            'description' => $article->seo_description ?: ($article->excerpt ?: SeoSettings::general()['default_description']),
            'crumbs' => $crumbs,
            'ogImage' => $article->coverUrl('1200') ? url($article->coverUrl('1200')) : null,
            'jsonld' => array_filter([$seo->article($article), $seo->breadcrumbs($crumbs), $seo->faq($faqs)]),
        ])->setLastModified($article->updated_at);
    }
}
