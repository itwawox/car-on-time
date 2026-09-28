<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Car;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompareTest extends TestCase
{
    use RefreshDatabase;

    public function test_compare_page_shows_up_to_three_cars_and_is_noindex(): void
    {
        $brand = Brand::query()->create(['slug' => 'kia', 'name' => 'Kia']);
        $ids = collect(['Kia Rio', 'Kia Ceed', 'Kia K5', 'Kia Sportage'])->map(fn ($name, $i) => Car::query()->create([
            'brand_id' => $brand->id, 'name' => $name, 'slug' => str($name)->slug(), 'gearbox' => $i ? 'at' : 'mt', 'seats' => 5, 'status' => 'published',
        ])->id);

        $this->get('/sravnenie?ids='.$ids->implode(','))->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false)
            ->assertSee('Kia Rio')->assertSee('Kia K5')->assertDontSee('Kia Sportage')
            ->assertSee('Механика')->assertSee('data-compare-page', false);

        $this->get('/sravnenie')->assertOk()->assertSee('Пока нечего сравнивать');
    }

    public function test_compare_marks_the_better_value_with_a_difference_bar(): void
    {
        $brand = Brand::query()->create(['slug' => 'kia', 'name' => 'Kia']);
        $cheap = Car::query()->create(['brand_id' => $brand->id, 'name' => 'Rio', 'slug' => 'rio', 'gearbox' => 'at', 'seats' => 5, 'status' => 'published', 'deposit' => 5000]);
        $pricey = Car::query()->create(['brand_id' => $brand->id, 'name' => 'K5', 'slug' => 'k5', 'gearbox' => 'at', 'seats' => 5, 'status' => 'published', 'deposit' => 5000]);
        $cheap->prices()->create(['price' => 1500, 'days_from' => 1, 'days_to' => null]);
        $pricey->prices()->create(['price' => 3000, 'days_from' => 1, 'days_to' => null]);

        $html = $this->get('/sravnenie?ids='.$cheap->id.','.$pricey->id)->assertOk()->getContent();

        // Цена: дешевле — «лучше», полоски пропорциональны (1500 из 3000 = 0.5)
        $this->assertMatchesRegularExpression('/<td class="is-best">\s*от 1 500 ₽\/сут\.\s*<span class="compare-best">лучше<\/span>/u', $html);
        $this->assertStringContainsString('style="--bar: 0.5"', $html);
        // Одинаковые места и залог — без полосок и без «лучше»
        $this->assertSame(1, substr_count($html, 'class="compare-best"'));
        $this->assertSame(2, substr_count($html, 'compare-bar-line'));
    }
}
