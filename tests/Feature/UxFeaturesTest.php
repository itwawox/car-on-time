<?php

namespace Tests\Feature;

use App\Models\BodyType;
use App\Models\Brand;
use App\Models\Car;
use App\Models\CarClass;
use App\Models\Lead;
use App\Models\Location;
use App\Models\Promotion;
use App\Support\Fleet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UxFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private function car(string $name, int $price, array $extra = [], string $class = 'ekonom'): Car
    {
        $brand = Brand::query()->firstOrCreate(['slug' => 'kia'], ['name' => 'Kia']);
        $body = BodyType::query()->firstOrCreate(['slug' => 'sedan'], ['name' => 'Седан']);
        $cls = CarClass::query()->firstOrCreate(['slug' => $class], ['name' => ucfirst($class)]);
        $car = Car::query()->create(['brand_id' => $brand->id, 'body_type_id' => $body->id, 'name' => $name, 'slug' => str($name)->slug(),
            'gearbox' => 'at', 'fuel' => 'petrol', 'seats' => 5, 'status' => 'published', 'min_days' => 1, ...$extra]);
        $car->classes()->attach($cls);
        $car->prices()->create(['price' => $price, 'days_from' => 1, 'days_to' => null]);
        Fleet::forget();

        return $car;
    }

    public function test_filters_combine_and_count_endpoint_matches(): void
    {
        $this->car('Cheap Auto', 1500);
        $this->car('Cheap Manual', 1400, ['gearbox' => 'mt']);
        $this->car('Big Van', 3000, ['seats' => 8]);
        $this->car('Boss', 6000, [], 'biznes');

        $this->getJson('/katalog/count?kp=at&price_max=3000')->assertJson(['count' => 2]);
        $this->getJson('/katalog/count?seats=7')->assertJson(['count' => 1]);
        $this->getJson('/katalog/count?class[]=biznes')->assertJson(['count' => 1]);

        $this->get('/katalog?kp=at&price_max=3000')->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false)
            ->assertSee('Cheap Auto')->assertSee('Big Van')->assertDontSee('Cheap Manual')->assertDontSee('Boss')
            ->assertSee('до 3 000 ₽');
        // Массив class[] — фильтр, а не старый редирект на страницу класса
        $this->get('/katalog?class[]=biznes')->assertOk()->assertSee('Boss');
        $this->get('/katalog?class=biznes')->assertRedirect('/klass/biznes');
    }

    public function test_batch_quote_adds_delivery_to_selected_place(): void
    {
        $car = $this->car('Rio', 1000);
        $place = Location::query()->create(['name' => 'Ялта', 'slug' => 'yalta', 'type' => 'city', 'is_active' => true,
            'price_1_day' => 3000, 'price_2_days' => 3000, 'price_3plus' => 2000, 'night_price' => 0, 'hours_from' => '00:00', 'hours_to' => '23:59']);

        $this->getJson('/quote/batch?ids='.$car->id.'&starts_at=2030-07-01T10:00&ends_at=2030-07-04T10:00&pickup_location_id='.$place->id)
            ->assertJsonPath('prices.'.$car->id.'.total', 3000)
            ->assertJsonPath('prices.'.$car->id.'.delivery', 4000); // туда и обратно
    }

    public function test_promotions_visibility_and_sitemap(): void
    {
        Promotion::query()->create(['slug' => 'leto', 'title' => 'Лето −10%', 'is_published' => true, 'promo_code' => 'LETO', 'ends_at' => now()->addMonth()]);
        Promotion::query()->create(['slug' => 'old', 'title' => 'Прошлая', 'is_published' => true, 'ends_at' => now()->subDay()]);
        Promotion::query()->create(['slug' => 'draft', 'title' => 'Черновик', 'is_published' => false]);

        $this->get('/akcii')->assertOk()->assertSee('Лето −10%')->assertDontSee('Прошлая')->assertDontSee('Черновик');
        $this->get('/akcii/leto')->assertOk()->assertSee('LETO');
        $this->get('/akcii/old')->assertNotFound();
        $this->get('/akcii/draft')->assertNotFound();
        $this->get('/sitemap.xml')->assertSee('/akcii/leto')->assertDontSee('/akcii/old');
        $this->get('/')->assertSee('Лето −10%');
    }

    public function test_callback_and_corporate_leads(): void
    {
        $this->postJson('/obratnyj-zvonok', ['type' => 'callback', 'phone' => '+7 (978) 948-48-48', 'pd_consent' => '1'])->assertOk()->assertJson(['ok' => true]);
        $this->postJson('/obratnyj-zvonok', ['type' => 'callback', 'phone' => '+7 (978', 'pd_consent' => '1'])->assertUnprocessable();
        $this->post('/obratnyj-zvonok', ['type' => 'corporate', 'phone' => '89789484848', 'company' => 'ООО Ромашка', 'message' => '3 машины', 'pd_consent' => '1'])
            ->assertRedirect()->assertSessionHas('lead_sent');

        $this->postJson('/obratnyj-zvonok', ['type' => 'callback', 'phone' => '+7 (978) 111-22-33'])->assertUnprocessable()->assertJsonValidationErrors('pd_consent');
        $this->assertSame(2, Lead::query()->count());
        $this->assertSame('ООО Ромашка', Lead::query()->where('type', 'corporate')->value('company'));
        $this->get('/yurlicam')->assertOk()->assertSee('Заявка для компании');
    }

    public function test_favorites_page_is_noindex_and_lists_cars(): void
    {
        $a = $this->car('Fav One', 1500);

        $this->get('/izbrannoe?ids='.$a->id)->assertOk()->assertSee('Fav One')->assertSee('<meta name="robots" content="noindex,follow">', false);
        $this->get('/izbrannoe')->assertOk()->assertSee('Здесь пока пусто');
    }

    public function test_car_card_heading_is_not_taken_from_the_page_variables(): void
    {
        $a = $this->car('Fav Title', 1500);

        // Страница «Избранное» передаёт в шаблон свой $heading — он не должен становиться тегом заголовка карточки
        $this->get('/izbrannoe?ids='.$a->id)->assertOk()
            ->assertSee('<h3 class="car-card-title">', false)
            ->assertDontSee('<Избранное', false);
    }

    public function test_one_car_asks_to_add_another_before_comparing(): void
    {
        $a = $this->car('Cmp One', 1500);
        $b = $this->car('Cmp Two', 1600);

        $this->get('/izbrannoe?ids='.$a->id)->assertOk()
            ->assertSee('Добавьте ещё машину для сравнения')
            ->assertDontSee('Сравнить первые');
        $this->get('/sravnenie?ids='.$a->id)->assertOk()->assertSee('Добавьте ещё хотя бы одну машину');

        $this->get('/izbrannoe?ids='.$a->id.','.$b->id)->assertOk()->assertSee('Сравнить 2 машины');
        $this->get('/sravnenie?ids='.$a->id.','.$b->id)->assertOk()->assertDontSee('Добавьте ещё хотя бы одну машину');
    }
}
