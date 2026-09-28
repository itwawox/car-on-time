<?php

namespace Tests\Feature;

use App\Models\BodyType;
use App\Models\Brand;
use App\Models\Car;
use App\Models\CarClass;
use App\Support\Fleet;
use App\Support\HomeScenarios;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeUxTest extends TestCase
{
    use RefreshDatabase;

    private function car(string $name, int $price, string $class, string $body, array $extra = []): Car
    {
        $brand = Brand::query()->firstOrCreate(['slug' => 'test'], ['name' => 'Test']);
        $bodyType = BodyType::query()->firstOrCreate(['slug' => $body], ['name' => ucfirst($body)]);
        $cls = CarClass::query()->firstOrCreate(['slug' => $class], ['name' => ucfirst($class)]);
        $car = Car::query()->create(['brand_id' => $brand->id, 'body_type_id' => $bodyType->id, 'name' => $name, 'slug' => str($name)->slug(),
            'gearbox' => 'at', 'fuel' => 'petrol', 'seats' => 5, 'status' => 'published', 'min_days' => 2, ...$extra]);
        $car->classes()->attach($cls);
        $car->prices()->create(['price' => $price, 'days_from' => 1, 'days_to' => null]);
        Fleet::forget();

        return $car;
    }

    private function fleet(): void
    {
        $this->car('Solaris', 1700, 'ekonom', 'sedan');
        $this->car('Duster 4WD', 2400, 'srednij', 'krossover', ['drivetrain' => '4wd']);
        $this->car('Camry', 4500, 'biznes', 'sedan');
        $this->car('Cabrio', 5500, 'biznes', 'kabriolet');
        $this->car('Vito', 3500, 'srednij', 'miniven', ['seats' => 8]);
    }

    public function test_scenarios_have_real_counts_prices_and_working_links(): void
    {
        $this->fleet();
        $scenarios = collect(HomeScenarios::all())->keyBy('key');

        $this->assertSame(1, $scenarios['mountains']['count']);
        $this->assertSame(2400, $scenarios['mountains']['price']);
        $this->assertSame(2, $scenarios['business']['count']);
        $this->assertSame(1, $scenarios['fun']['count']);

        // Ссылка сценария открывает каталог с теми же машинами
        $this->get($scenarios['mountains']['url'])->assertOk()->assertSee('Duster 4WD')->assertDontSee('Solaris');
        $this->get($scenarios['business']['url'])->assertOk()->assertSee('Camry')->assertSee('Cabrio')->assertDontSee('Solaris');
    }

    public function test_home_order_hints_trust_strip_and_popular_tabs(): void
    {
        $this->fleet();

        $this->get('/')->assertOk()
            ->assertSee('Часто ищут:')
            ->assertSee('Без предоплаты')
            ->assertDontSee('aria-label="Преимущества"', false)
            ->assertSeeInOrder(['Популярные машины', 'Для какой поездки?', 'Как это работает'])
            ->assertSee('id="pt-biznes"', false)
            ->assertSee('7+ мест')
            ->assertSee('data-home-bar', false)
            ->assertSee('/faq#g-rules', false)
            ->assertSee('Не нашли ответ?');

        foreach (HomeScenarios::hints() as $hint) {
            $this->get(str_replace(url('/'), '', $hint['url']) ?: '/')->assertOk();
        }
    }

    public function test_car_page_has_back_link_and_booking_hints(): void
    {
        $this->fleet();

        $this->get('/avto/solaris')->assertOk()
            ->assertSee('data-back-results', false)
            ->assertSee('Эту машину сдаём от 2 суток')
            ->assertSee('перезвоним за 15 минут');
    }
}
