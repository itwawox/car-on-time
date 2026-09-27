<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Car;
use App\Models\Season;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_and_city_and_quiz(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertSee('urlset', false);
        $this->get('/arenda-avto-v-yalta')->assertOk()->assertSee('Аренда авто в Ялте');
        $this->get('/podbor')->assertOk()->assertSee('Подберём авто');
        $this->get('/faq')->assertOk();
        $this->get('/robots.txt')->assertOk();
    }

    public function test_live_quote_json(): void
    {
        $brand = Brand::query()->create(['name' => 'Renault', 'slug' => 'renault']);
        $season = Season::query()->create([
            'name' => 'Лето',
            'starts_month' => 6,
            'starts_day' => 1,
            'ends_month' => 10,
            'ends_day' => 4,
            'sort' => 1,
        ]);
        $car = Car::query()->create([
            'brand_id' => $brand->id,
            'name' => 'Logan',
            'slug' => 'logan-test',
            'gearbox' => 'mt',
            'deposit' => 5000,
            'min_days' => 2,
            'status' => 'published',
        ]);
        $car->prices()->create([
            'season_id' => $season->id,
            'days_from' => 2,
            'days_to' => 3,
            'price' => 1400,
        ]);

        $this->getJson('/quote?car_id='.$car->id.'&starts_at=2026-07-01T10:00&ends_at=2026-07-04T10:00')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('total', 4200);
    }

    public function test_catalog_pagination_uses_pretty_urls(): void
    {
        $brand = Brand::query()->create(['name' => 'Kia', 'slug' => 'kia']);
        for ($i = 1; $i <= 50; $i++) {
            Car::query()->create([
                'brand_id' => $brand->id,
                'name' => 'Car '.$i,
                'slug' => 'car-'.$i,
                'gearbox' => 'at',
                'status' => 'published',
                'sort' => $i,
            ]);
        }

        $this->get('/katalog?page=2')->assertRedirect('/katalog/page/2');
        $this->get('/katalog/page/1')->assertRedirect('/katalog');

        $this->get('/katalog/page/2')
            ->assertOk()
            ->assertSee('страница 2', false)
            ->assertSee('Назад')
            ->assertSee('Вперёд')
            ->assertDontSee('pagination.previous')
            ->assertDontSee('Showing')
            ->assertSee('/katalog/page/3', false);
    }
}
