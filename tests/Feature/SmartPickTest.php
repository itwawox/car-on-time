<?php

namespace Tests\Feature;

use App\Models\BodyType;
use App\Models\Brand;
use App\Models\Car;
use App\Models\CarClass;
use App\Models\CarModel;
use App\Models\Setting;
use App\Support\CarAlternatives;
use App\Support\CatalogListing;
use App\Support\Fleet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmartPickTest extends TestCase
{
    use RefreshDatabase;

    private BodyType $sedan;

    private CarClass $econom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sedan = BodyType::query()->firstOrCreate(['slug' => 'sedan'], ['name' => 'Седан']);
        $this->econom = CarClass::query()->firstOrCreate(['slug' => 'ekonom'], ['name' => 'Эконом']);
    }

    private function car(string $name, int $price, array $extra = [], array $model = []): Car
    {
        $brand = Brand::query()->firstOrCreate(['slug' => 'test'], ['name' => 'Test']);
        $carModel = CarModel::query()->create([
            'brand_id' => $brand->id, 'name' => $name, 'slug' => str($name)->slug().'-m', 'overview' => '—',
            'power_hp' => 100, 'consumption_mixed' => 7.0, 'trunk_l' => 450, 'fuel_grade' => '92', ...$model,
        ]);
        $car = Car::query()->create([
            'brand_id' => $brand->id, 'car_model_id' => $carModel->id, 'body_type_id' => $this->sedan->id,
            'name' => $name, 'slug' => str($name)->slug(), 'gearbox' => 'at', 'fuel' => 'petrol', 'seats' => 5,
            'status' => 'published', 'min_days' => 1, ...$extra,
        ]);
        $car->classes()->attach($this->econom);
        $car->prices()->create(['price' => $price, 'days_from' => 1, 'days_to' => 30]);
        Fleet::forget();

        return $car;
    }

    public function test_facts_take_car_value_first_then_model_and_count_fuel_cost(): void
    {
        $car = $this->car('Solaris', 1700, ['power_hp' => 123]);
        Setting::put('fuel_price_92', 60);

        $facts = $car->fresh()->facts();
        $this->assertSame(123, $facts->power);
        $this->assertFalse($facts->isTypical('power'));
        $this->assertTrue($facts->isTypical('consumption'));
        $this->assertSame(420, $facts->costPer100km()); // 7 л × 60 ₽
        $this->assertSame(1050, $facts->fuelCost(250));
    }

    public function test_alternatives_find_cheaper_stronger_and_economy_with_deltas(): void
    {
        $base = $this->car('Base', 2000);
        $this->car('Cheap', 1700);
        $this->car('Strong', 2300, [], ['power_hp' => 150]);
        $this->car('Eco', 2100, [], ['consumption_mixed' => 5.5]);
        $this->car('Manual', 1500, ['gearbox' => 'mt']);

        $alts = collect(CarAlternatives::for($base->fresh()))->keyBy('key');

        $this->assertSame('Cheap', $alts['cheaper']['car']['name']);
        $this->assertSame('−300 ₽/сут', $alts['cheaper']['deltas'][0]['text']);
        $this->assertSame('Strong', $alts['stronger']['car']['name']);
        $this->assertSame('+50 л.с.', $alts['stronger']['deltas'][0]['text']);
        $this->assertSame('Eco', $alts['economy']['car']['name']);
        // Автомат не предлагает механику, и одна машина не повторяется в двух сценариях
        $this->assertNotContains('Manual', $alts->pluck('car.name')->all());
        $this->assertSame($alts->count(), $alts->pluck('car.id')->unique()->count());

        $this->get('/avto/base')->assertOk()->assertSee('Сравните с похожими')->assertSee('−300 ₽/сут');
    }

    public function test_manual_car_gets_automatic_alternative(): void
    {
        $manual = $this->car('Manual', 1500, ['gearbox' => 'mt']);
        $this->car('Auto', 1650);

        $alts = collect(CarAlternatives::for($manual->fresh()))->keyBy('key');
        $this->assertSame('Auto', $alts['automatic']['car']['name'] ?? $alts->first()['car']['name']);
    }

    public function test_batch_quote_returns_period_totals_and_respects_min_days(): void
    {
        $a = $this->car('A', 1000);
        $b = $this->car('B', 2000, ['min_days' => 5]);

        $this->getJson('/quote/batch?ids='.$a->id.','.$b->id.'&starts_at=2030-07-01T10:00&ends_at=2030-07-04T10:00')
            ->assertOk()
            ->assertJsonPath('prices.'.$a->id.'.total', 3000)
            ->assertJsonPath('prices.'.$a->id.'.days', 3)
            ->assertJsonPath('prices.'.$b->id.'.ok', false);
    }

    public function test_catalog_sorting_is_noindex_and_orders_by_power(): void
    {
        $this->car('Weak', 1500, [], ['power_hp' => 90]);
        $this->car('Mighty', 1600, [], ['power_hp' => 200]);

        $this->assertSame('Mighty', Car::find(CatalogListing::sortedIds('power')[0])->name);
        $this->get('/katalog?sort=power')->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false)
            ->assertSeeInOrder(['Mighty', 'Weak']);
        $this->get('/katalog')->assertSee('Подробно')->assertSee('ctable', false);
    }

    public function test_similar_to_viewed_excludes_viewed_cars(): void
    {
        $a = $this->car('Viewed', 1500);
        $this->car('Other', 1550);

        $this->getJson('/podbor/pohozhie?ids='.$a->id)->assertOk()
            ->assertJsonPath('cars.0.name', 'Other')
            ->assertJsonMissing(['name' => 'Viewed']);
    }
}
