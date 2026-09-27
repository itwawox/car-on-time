<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Brand;
use App\Models\Car;
use App\Models\CarClass;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromoCodeTest extends TestCase
{
    use RefreshDatabase;

    private Car $car;

    protected function setUp(): void
    {
        parent::setUp();

        $brand = Brand::query()->create(['slug' => 'kia', 'name' => 'Kia']);
        $this->car = Car::query()->create(['brand_id' => $brand->id, 'name' => 'Kia Rio', 'slug' => 'kia-rio', 'gearbox' => 'at', 'seats' => 5, 'status' => 'published', 'min_days' => 1]);
        $this->car->prices()->create(['price' => 2000, 'days_from' => 1, 'days_to' => null]);
    }

    private function quote(string $code, int $days = 3): array
    {
        return $this->getJson('/quote?'.http_build_query([
            'car_id' => $this->car->id, 'promo_code' => $code,
            'starts_at' => now()->addDays(2)->format('Y-m-d 10:00'), 'ends_at' => now()->addDays(2 + $days)->format('Y-m-d 10:00'),
        ]))->assertOk()->json();
    }

    public function test_percent_code_discounts_rent_in_quote_and_booking(): void
    {
        PromoCode::query()->create(['code' => 'summer', 'discount_type' => 'percent', 'discount_value' => 10]);

        $quote = $this->quote(' Summer ');
        $this->assertSame(600, $quote['discount']);
        $this->assertSame(5400, $quote['total']);
        $this->assertSame(['code' => 'SUMMER', 'ok' => true, 'label' => '−10%'], $quote['promo']);

        $this->post('/zayavka', [
            'car_id' => $this->car->id, 'phone' => '+79780000000', 'pd_consent' => '1', 'promo_code' => 'summer',
            'starts_at' => now()->addDays(2)->format('Y-m-d\T10:00'), 'ends_at' => now()->addDays(5)->format('Y-m-d\T10:00'),
        ])->assertRedirect();

        $booking = Booking::query()->sole();
        $this->assertSame([600, 5400], [$booking->discount, $booking->total]);
    }

    public function test_fixed_discount_never_exceeds_rent(): void
    {
        PromoCode::query()->create(['code' => 'BIG', 'discount_type' => 'fixed', 'discount_value' => 50000]);

        $this->assertSame(6000, $this->quote('big')['discount']);
    }

    public function test_code_restrictions_are_explained_and_give_no_discount(): void
    {
        $vip = CarClass::query()->create(['name' => 'Бизнес', 'slug' => 'biznes']);
        PromoCode::query()->create(['code' => 'LONG', 'discount_type' => 'percent', 'discount_value' => 5, 'min_days' => 7]);
        PromoCode::query()->create(['code' => 'OLD', 'discount_type' => 'percent', 'discount_value' => 5, 'ends_at' => now()->subDay()]);
        PromoCode::query()->create(['code' => 'VIP', 'discount_type' => 'percent', 'discount_value' => 5, 'car_class_ids' => [$vip->id]]);
        PromoCode::query()->create(['code' => 'ONCE', 'discount_type' => 'percent', 'discount_value' => 5, 'max_uses' => 1]);
        Booking::query()->create(['car_id' => $this->car->id, 'phone' => '1', 'status' => 'confirmed', 'promo_code' => 'ONCE', 'total' => 1, 'source' => 'card', 'starts_at' => now(), 'ends_at' => now()->addDay()]);

        $this->assertStringContainsString('от 7 суток', $this->quote('long')['promo']['error']);
        $this->assertStringContainsString('закончился', $this->quote('old')['promo']['error']);
        $this->assertStringContainsString('не действует для этой машины', $this->quote('vip')['promo']['error']);
        $this->assertStringContainsString('уже использован', $this->quote('once')['promo']['error']);
        $this->assertStringContainsString('Такого промокода нет', $this->quote('nope')['promo']['error']);
        $this->assertSame(0, $this->quote('long')['discount']);
    }

    public function test_admin_can_manage_codes(): void
    {
        PromoCode::query()->create(['code' => 'SUMMER', 'discount_type' => 'percent', 'discount_value' => 10]);
        $this->actingAs(User::factory()->role('editor')->create());

        $this->get('/admin/promo-codes')->assertOk()->assertSee('SUMMER')->assertSee('−10%');
    }
}
