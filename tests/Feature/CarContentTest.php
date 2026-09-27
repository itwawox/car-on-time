<?php

namespace Tests\Feature;

use App\Models\BodyType;
use App\Models\Brand;
use App\Models\Car;
use App\Models\CarClass;
use App\Models\CarModel;
use App\Models\City;
use App\Models\Season;
use App\Support\CarDescription;
use App\Support\CarSeoLinks;
use App\Support\CarSpecs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarContentTest extends TestCase
{
    use RefreshDatabase;

    private function car(string $brand, string $model, string $name, array $extra = [], array $modelExtra = []): Car
    {
        $b = Brand::query()->firstOrCreate(['slug' => str($brand)->slug()], ['name' => $brand]);
        $slug = $modelExtra['body'] ?? 'sedan';
        $body = BodyType::query()->firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug), 'sort' => 1]);
        $m = CarModel::query()->firstOrCreate(['slug' => str($brand.' '.$model)->slug()], [
            'brand_id' => $b->id, 'name' => $model, 'body_type_id' => $body->id,
            'drivetrain' => $modelExtra['drive'] ?? 'fwd', 'drivetrain_note' => $modelExtra['note'] ?? null,
            'overview' => "{$brand} {$model} — надёжная машина для Крыма.", 'strengths' => ['Экономичность', 'Полный привод xDrive'],
        ]);
        $car = Car::query()->create([
            'brand_id' => $b->id, 'car_model_id' => $m->id, 'body_type_id' => $body->id, 'name' => $name,
            'slug' => str($name)->slug(), 'gearbox' => 'at', 'seats' => 5, 'status' => 'published',
            'drivetrain' => $modelExtra['drive'] ?? 'fwd', 'deposit' => 5000, 'min_days' => 2, 'daily_km' => 300, ...$extra,
        ]);
        $class = CarClass::query()->firstOrCreate(['slug' => 'ekonom'], ['name' => 'Эконом', 'sort' => 1]);
        $car->classes()->attach($class);
        $season = Season::query()->firstOrCreate(['name' => 'Год'], ['starts_month' => 1, 'starts_day' => 1, 'ends_month' => 12, 'ends_day' => 31, 'sort' => 1]);
        $car->prices()->create(['season_id' => $season->id, 'days_from' => 1, 'days_to' => 30, 'price' => 1700]);

        return $car->fresh();
    }

    public function test_specs_are_parsed_from_the_name(): void
    {
        $this->assertTrue(CarSpecs::explicit4wd('Bmw X6 (F16) XDrive35i'));
        $this->assertTrue(CarSpecs::explicit4wd('Mercedes GL 400 4MA (7 МЕСТ)'));
        $this->assertTrue(CarSpecs::explicit4wd('Mercedes S-Class S 350 4MATIC (Дизель)'));
        $this->assertTrue(CarSpecs::explicit4wd('Lada 4x4 (new 2024) (М/T)'));
        $this->assertFalse(CarSpecs::explicit4wd('Hyundai Solaris(A/T)'));
        $this->assertSame([2017, 2020], CarSpecs::years('Hyundai Creta(2017-2020) 2.0'));
        $this->assertSame([2023, null], CarSpecs::years('Kaiyi E5 (2023) Luxury+'));
        $this->assertSame('1.6 л, 123 л.с.', CarSpecs::engine('Kia Rio X-line (2018-2020) 1.6л. 123л.с.'));
        $this->assertSame('1.4 TSI', CarSpecs::engine('Volkswagen Tiguan (1.4 tsi)'));
        $this->assertSame(7, CarSpecs::seats('Lada Largus(7 мест) 2018-2020 (М/Т)'));
    }

    public function test_description_matches_the_car(): void
    {
        $at = $this->car('Hyundai', 'Solaris', 'Hyundai Solaris (A/T)');
        $mt = $this->car('Hyundai', 'Solaris', 'Hyundai Solaris (M/T)', ['gearbox' => 'mt']);

        $a = strip_tags(CarDescription::build($at));
        $m = strip_tags(CarDescription::build($mt));

        $this->assertStringContainsString('автоматическая коробка передач', $a);
        $this->assertStringContainsString('механическая коробка передач', $m);
        $this->assertStringContainsString('передний привод', $a);
        $this->assertStringNotContainsString('Полный привод пригодится', $a);
        $this->assertStringContainsString('от 1 700 ₽', $a);
        $this->assertNotSame($a, $m);
        // Регистр в названиях технологий сохраняется
        $this->assertStringContainsString('полный привод xDrive', $a);
    }

    public function test_4wd_and_note_are_described_without_duplicates(): void
    {
        $car = $this->car('BMW', 'X5', 'Bmw X5 xDrive35i', [], ['drive' => '4wd', 'note' => 'полный привод xDrive', 'body' => 'krossover']);

        $text = strip_tags(CarDescription::build($car));
        $this->assertStringContainsString('полный привод xDrive', $text);
        $this->assertStringNotContainsString('полный привод (полный привод', $text);
    }

    public function test_seo_links_point_only_to_published_pages(): void
    {
        $solo = $this->car('Porsche', 'Boxster', 'Porsche Boxster S');
        City::query()->where('slug', '!=', 'simferopol-aeroport')->update(['is_published' => false]);
        CarSeoLinks::forget();

        $block = CarSeoLinks::build($solo);

        $this->assertStringContainsString(route('home'), $block['html']);
        $this->assertStringContainsString('/klass/ekonom', $block['html']);
        $this->assertStringContainsString('/arenda-avto-v-simferopol-aeroport', $block['html']);
        $this->assertStringNotContainsString('/marka/porsche', $block['html']); // у марки одна машина
        $this->assertStringNotContainsString('/arenda-avto-v-yalta', $block['html']);
        $this->assertLessThanOrEqual(7, $block['links']);
        $this->assertGreaterThanOrEqual(4, $block['links']);
    }

    public function test_describe_command_keeps_manual_text_unless_forced(): void
    {
        $generic = $this->car('Kia', 'Rio', 'Kia Rio', ['description' => 'Аренда Kia Rio в Крыму от Car on Time. Наличие подтвердим за 15 минут после заявки.']);
        $manual = $this->car('Kia', 'Rio', 'Kia Rio X', ['description' => '<p>Наш любимый Rio, свежая резина.</p>']);

        $this->artisan('cars:describe')->assertSuccessful();

        $this->assertStringContainsString('автоматическая коробка передач', $generic->fresh()->description);
        $this->assertSame('<p>Наш любимый Rio, свежая резина.</p>', $manual->fresh()->description);

        $this->artisan('cars:describe', ['--force' => true])->assertSuccessful();
        $this->assertStringContainsString('автоматическая коробка передач', $manual->fresh()->description);
    }

    public function test_car_page_shows_specs_description_and_seo_block(): void
    {
        $car = $this->car('Hyundai', 'Solaris', 'Hyundai Solaris (A/T)');
        $this->artisan('cars:describe');

        $this->get('/avto/'.$car->slug)
            ->assertOk()
            ->assertSee('Привод')
            ->assertSee('Передний')
            ->assertSee('Об автомобиле')
            ->assertSee('Аренда Hyundai Solaris (A/T) в Крыму')
            ->assertSee('"driveWheelConfiguration":"https://schema.org/FrontWheelDriveConfiguration"', false);
    }
}
