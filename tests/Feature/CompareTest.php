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
}
