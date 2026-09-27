<?php

namespace Tests\Feature;

use App\Models\BodyType;
use App\Models\Brand;
use App\Models\Car;
use App\Models\CarClass;
use App\Models\User;
use App\Support\Fleet;
use App\Support\QuizMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizTest extends TestCase
{
    use RefreshDatabase;

    private function car(string $name, int $price, string $class, string $body = 'sedan', array $extra = []): Car
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
        $this->car('Logan MT', 1400, 'ekonom', 'sedan', ['gearbox' => 'mt']);
        $this->car('Solaris AT', 1700, 'ekonom');
        $this->car('Octavia', 2500, 'srednij', 'liftbek');
        $this->car('Camry', 4500, 'biznes');
        $this->car('BMW 5', 7000, 'biznes');
        $this->car('Duster 4WD', 2400, 'srednij', 'krossover', ['drivetrain' => '4wd', 'clearance_mm' => 210]);
        $this->car('Vito', 3500, 'srednij', 'miniven', ['seats' => 8]);
        $this->car('Cabrio', 5500, 'biznes', 'kabriolet', ['power_hp' => 250]);
    }

    public function test_business_trip_never_suggests_cheap_economy_manual(): void
    {
        $this->fleet();
        $m = QuizMatcher::match(['who' => 'business', 'trip' => ['city'], 'priority' => 'comfort', 'gearbox' => 'any', 'luggage' => 'suitcases', 'budget' => 'any']);

        $names = array_filter([$m['best']['name'] ?? null, $m['cheaper']['name'] ?? null, $m['comfort']['name'] ?? null]);
        $this->assertNotContains('Logan MT', $names);
        $this->assertNotContains('Solaris AT', $names);
        $this->assertContains('biznes', $m['best']['class_slugs']);
    }

    public function test_group_gets_seats_mountains_get_4wd_and_fun_gets_cabrio(): void
    {
        $this->fleet();

        $this->assertSame('Vito', QuizMatcher::match(['who' => 'group', 'trip' => [], 'priority' => 'balance', 'gearbox' => 'any', 'luggage' => 'lots', 'budget' => 'any'])['best']['name']);
        $this->assertSame('Duster 4WD', QuizMatcher::match(['who' => 'family', 'trip' => ['mountains'], 'priority' => 'balance', 'gearbox' => 'any', 'luggage' => 'suitcases', 'budget' => 'any'])['best']['name']);
        $this->assertSame('Cabrio', QuizMatcher::match(['who' => 'solo', 'trip' => ['coast'], 'priority' => 'fun', 'gearbox' => 'any', 'luggage' => 'light', 'budget' => 'any'])['best']['name']);
    }

    public function test_automatic_only_is_respected_and_budget_relaxes_honestly(): void
    {
        $this->fleet();
        $m = QuizMatcher::match(['who' => 'solo', 'trip' => ['city'], 'priority' => 'save', 'gearbox' => 'at', 'luggage' => 'light', 'budget' => 'any']);
        $this->assertSame('Solaris AT', $m['best']['name']);

        // 8 мест при самом маленьком бюджете — машин нет, бюджет ослабляется и это видно
        $m = QuizMatcher::match(['who' => 'group', 'trip' => [], 'priority' => 'save', 'gearbox' => 'any', 'luggage' => 'lots', 'budget' => 'b1']);
        $this->assertSame(['budget'], $m['relaxed']);
        $this->assertSame('Vito', $m['best']['name']);
    }

    public function test_quiz_pages_and_count_endpoint(): void
    {
        $this->fleet();

        $this->get('/podbor')->assertOk()->assertSee('Кто едет?')->assertSee('Деловая поездка')->assertSee('data-quiz', false);
        $this->getJson('/podbor/count?who=group')->assertJson(['count' => 1]);
        $this->get('/podbor/rezultat?who=business&trip[]=city&priority=comfort&gearbox=at&luggage=suitcases&budget=any')
            ->assertOk()->assertSee('Лучшее совпадение')->assertSee('совпадение')->assertDontSee('Logan MT');
        // Старые ссылки «Продолжить подбор» не ломаются
        $this->get('/podbor/rezultat?who=business&where=sea&budget=high&kp=at')->assertOk();
    }

    public function test_quiz_texts_are_editable_in_settings_page(): void
    {
        config(['app.env' => 'local']);
        $this->actingAs(User::factory()->create());
        $this->get('/admin/site-settings')->assertOk()->assertSee('Подбор: квиз');
    }
}
