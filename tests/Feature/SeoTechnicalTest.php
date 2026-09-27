<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleRedirects;
use App\Models\Article;
use App\Models\Brand;
use App\Models\Car;
use App\Models\City;
use App\Models\Setting;
use App\Models\User;
use App\Support\Seo\IndexNow;
use App\Support\Seo\SeoSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class SeoTechnicalTest extends TestCase
{
    use RefreshDatabase;

    private function car(string $brand, string $name, array $extra = []): Car
    {
        $b = Brand::query()->firstOrCreate(['slug' => str($brand)->slug()], ['name' => $brand]);

        return Car::query()->create(['brand_id' => $b->id, 'name' => $name, 'slug' => str($name)->slug(), 'gearbox' => 'at', 'seats' => 5, 'status' => 'published', ...$extra]);
    }

    public function test_meta_templates_are_editable_and_entity_fields_win(): void
    {
        $this->car('Kia', 'Kia Rio');
        $templates = SeoSettings::templates();
        $templates['brand']['title'] = 'Прокат {name} — {count} авто{sep}{site}';
        Setting::put('seo_templates', $templates, SeoSettings::GROUP);

        $this->get('/marka/kia')->assertSee('<title>Прокат Kia — 1 авто | Car on Time</title>', false);

        Brand::query()->where('slug', 'kia')->first()->update(['seo_title' => 'Свой заголовок Kia']);
        $this->get('/marka/kia')->assertSee('<title>Свой заголовок Kia</title>', false);
    }

    public function test_pagination_title_keeps_brand_name_intact(): void
    {
        $this->assertSame('Аренда Kia в Крыму — страница 2 | Car on Time', SeoSettings::paginate('Аренда Kia в Крыму | Car on Time', 2));
    }

    public function test_sitemap_includes_new_content_automatically_and_skips_empty_or_hidden(): void
    {
        Article::query()->delete(); // статьи, опубликованные миграцией, здесь не нужны
        $car = $this->car('Kia', 'Kia Rio');
        Brand::query()->create(['name' => 'Empty', 'slug' => 'empty']);
        $this->car('Lada', 'Lada Hidden', ['status' => 'hidden']);

        $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
        $this->assertStringContainsString('/avto/kia-rio', $xml);
        $this->assertStringContainsString('/marka/kia', $xml);
        $this->assertStringContainsString('/arenda-avto-v-yalta', $xml);
        $this->assertStringNotContainsString('/marka/empty', $xml);
        $this->assertStringNotContainsString('lada-hidden', $xml);
        $this->assertStringContainsString('<lastmod>', $xml);

        // Новая статья попадает в карту сразу после публикации — кэш сбрасывается сам
        Article::query()->create(['slug' => 'most', 'title' => 'Крымский мост на арендованной машине', 'content' => '<p>Текст</p>', 'is_published' => true, 'published_at' => now()->subMinute()]);
        $this->assertStringContainsString('/stati/most', $this->get('/sitemap.xml')->getContent());

        $this->assertStringNotContainsString('/stati/', str_replace('/stati/most', '', $this->get('/sitemap.xml')->getContent()));
    }

    public function test_articles_section_and_old_guides_redirect(): void
    {
        $article = Article::query()->create([
            'slug' => 'aeroport', 'title' => 'Как взять авто в аэропорту', 'category' => 'Аэропорт и доставка',
            'content' => '<h2>Где стойка</h2><p>…</p><h2>Документы</h2><p>…</p><h2>Ночью</h2><p>…</p>',
            'faq' => [['question' => 'Можно ночью?', 'answer' => 'Да, по договорённости.']],
            'is_published' => true, 'published_at' => now()->subDay(),
        ]);
        Article::query()->create(['slug' => 'draft', 'title' => 'Черновик', 'content' => 'x', 'is_published' => false]);

        $this->get('/stati')->assertOk()->assertSee('Как взять авто в аэропорту')->assertDontSee('Черновик');
        $this->get('/stati/aeroport')->assertOk()
            ->assertSee('"@type":"BlogPosting"', false)
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('Содержание')
            ->assertHeader('Last-Modified');
        $this->get('/stati/draft')->assertNotFound();
        $this->get('/gid')->assertRedirect('/stati')->assertStatus(301);
        $this->get('/gid/aeroport')->assertRedirect('/stati/aeroport');
        $this->assertTrue($article->fresh()->is_published);
    }

    public function test_city_pages_come_from_database(): void
    {
        $this->get('/arenda-avto-v-yalta')->assertOk()->assertSee('Аренда авто в Ялте');
        City::query()->where('slug', 'yalta')->update(['is_published' => false]);
        $this->get('/arenda-avto-v-yalta')->assertNotFound();
    }

    public function test_trailing_slash_redirects_and_404_is_branded(): void
    {
        // Тестовый клиент Laravel сам обрезает слэш — проверяем middleware напрямую
        $response = (new HandleRedirects)->handle(
            Request::create('http://localhost/katalog/?utm_source=x'),
            fn () => response('ok'),
        );
        $this->assertSame(301, $response->getStatusCode());
        $this->assertStringEndsWith('/katalog?utm_source=x', (string) $response->headers->get('Location'));
        $this->get('/net-takoy-stranicy')->assertNotFound()->assertSee('Такой страницы нет')->assertSee('noindex', false);
    }

    public function test_robots_closes_non_production_and_indexnow_key_is_served(): void
    {
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /');

        $key = IndexNow::key();
        $this->get('/'.$key.'.txt')->assertOk()->assertSee($key);
        $this->get('/'.str_repeat('a', 32).'.txt')->assertNotFound();
    }

    public function test_conditional_get_returns_304(): void
    {
        $this->car('Kia', 'Kia Rio');
        $etag = $this->get('/katalog')->assertOk()->headers->get('ETag');

        $this->withHeaders(['If-None-Match' => $etag])->get('/katalog')->assertStatus(304);
    }

    public function test_admin_seo_pages_render(): void
    {
        config(['app.env' => 'local']);
        $this->actingAs(User::factory()->create());

        $this->get('/admin/seo/settings')->assertOk()->assertSee('Шаблоны мета-тегов');
        $this->get('/admin/cities')->assertOk()->assertSee('Ялта');
        $this->get('/admin/articles/create')->assertOk()->assertSee('Подборка машин');
        $this->get('/admin/brands/create')->assertOk()->assertSee('SEO-текст внизу страницы');
    }
}
