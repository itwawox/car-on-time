<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Car;
use App\Models\CarClass;
use App\Models\Season;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $season = Season::query()->create(['name' => 'Год', 'starts_month' => 1, 'starts_day' => 1, 'ends_month' => 12, 'ends_day' => 31, 'sort' => 1]);
        $economy = CarClass::query()->create(['name' => 'Эконом', 'slug' => 'ekonom', 'sort' => 1]);

        // Синонимы марок ведутся в админке — в тесте задаём их так же, через поле марки
        $aliases = ['hyundai' => 'хендай, хундай', 'lada' => 'лада, жигули'];

        $make = function (string $brand, string $slug, string $name, string $gearbox, int $price, int $seats = 5, array $extra = []) use ($season, $economy, $aliases) {
            $b = Brand::query()->firstOrCreate(['slug' => $slug], ['name' => $brand, 'search_aliases' => $aliases[$slug] ?? null]);
            $car = Car::query()->create([
                'brand_id' => $b->id,
                'name' => $name,
                'slug' => str($name)->slug(),
                'gearbox' => $gearbox,
                'seats' => $seats,
                'status' => 'published',
                ...$extra,
            ]);
            $car->classes()->attach($economy);
            $car->prices()->create(['season_id' => $season->id, 'days_from' => 1, 'days_to' => 30, 'price' => $price]);

            return $car;
        };

        $make('Hyundai', 'hyundai', 'Hyundai Solaris (М/T)', 'mt', 1500);
        $make('Hyundai', 'hyundai', 'Hyundai Creta', 'at', 2200);
        $make('Kia', 'kia', 'Kia Rio', 'at', 1700);
        $make('Toyota', 'toyota', 'Toyota Camry', 'at', 4000);
        $make('Lada', 'lada', 'Lada Largus (7 мест)', 'mt', 2000, 7);
        $make('Mazda', 'mazda', 'Mazda 6', 'at', 2900);
        $make('Renault', 'renault', 'Renault Logan скрытый', 'mt', 1400, 5, ['status' => 'hidden']);
    }

    private function names(string $query): array
    {
        $raw = Car::search($query)->raw();

        return Car::query()->whereIn('id', array_column($raw['results'], 'id'))->pluck('name')->all();
    }

    public function test_finds_by_russian_brand_spelling(): void
    {
        $this->assertEqualsCanonicalizing(['Hyundai Solaris (М/T)', 'Hyundai Creta'], $this->names('хендай'));
        $this->assertSame(['Toyota Camry'], $this->names('тойота камри'));
    }

    public function test_tolerates_typos_and_translit(): void
    {
        $this->assertSame(['Hyundai Solaris (М/T)'], $this->names('solyaris'));
        $this->assertSame(['Toyota Camry'], $this->names('toyta'));
        $this->assertSame(['Hyundai Solaris (М/T)'], $this->names('солярис'));
    }

    public function test_fixes_wrong_keyboard_layout(): void
    {
        $raw = Car::search('ьфявф')->raw();

        $this->assertSame('mazda', $raw['meta']['corrected']);
        $this->assertSame(1, $raw['total']);
    }

    public function test_applies_intent_filters(): void
    {
        $this->assertEqualsCanonicalizing(['Hyundai Creta', 'Kia Rio', 'Mazda 6'], $this->names('автомат до 3000'));
        $this->assertSame(['Lada Largus (7 мест)'], $this->names('7 мест'));
    }

    public function test_hidden_cars_are_not_found(): void
    {
        $this->assertSame([], $this->names('рено логан'));
    }

    public function test_search_page_is_noindex_and_lists_results(): void
    {
        $this->get('/poisk?q='.urlencode('киа'))
            ->assertOk()
            ->assertSee('Kia Rio')
            ->assertDontSee('Toyota Camry')
            ->assertSee('noindex,follow', false);
    }

    public function test_empty_search_redirects_to_catalog_pages(): void
    {
        $this->get('/poisk')->assertRedirect('/katalog');
        $this->get('/poisk?class=ekonom')->assertRedirect('/klass/ekonom');
        $this->get('/poisk?kp=at')->assertRedirect('/korobka/avtomat');
    }

    public function test_suggest_returns_brands_cars_and_understood_filters(): void
    {
        $this->getJson('/poisk/podskazki?q='.urlencode('хундай автомат'))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('cars.0.title', 'Hyundai Creta')
            ->assertJsonPath('brands.0.title', 'Hyundai')
            ->assertJsonPath('chips.0.label', 'Автомат');
    }

    public function test_suggest_without_query_returns_popular_brands(): void
    {
        $this->getJson('/poisk/podskazki')
            ->assertOk()
            ->assertJsonPath('popular.0.title', 'Hyundai');
    }
}
