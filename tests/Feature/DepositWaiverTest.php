<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Brand;
use App\Models\Car;
use App\Models\Extra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class DepositWaiverTest extends TestCase
{
    use RefreshDatabase;

    private Car $car;

    protected function setUp(): void
    {
        parent::setUp();

        $brand = Brand::query()->create(['slug' => 'kia', 'name' => 'Kia']);
        $this->car = Car::query()->create(['brand_id' => $brand->id, 'name' => 'Kia Rio', 'slug' => 'kia-rio', 'gearbox' => 'at', 'seats' => 5, 'status' => 'published', 'min_days' => 1, 'deposit' => 5000]);
        $this->car->prices()->create(['price' => 2000, 'days_from' => 1, 'days_to' => null]);
    }

    private function waiver(bool $active = true): Extra
    {
        $waiver = Extra::query()->where('slug', 'bez-zaloga')->firstOrFail();
        $waiver->update(['price_per_day' => 400, 'is_active' => $active]);

        return $waiver;
    }

    private function quote(array $extras = []): array
    {
        return $this->getJson('/quote?'.http_build_query([
            'car_id' => $this->car->id, 'extras' => $extras,
            'starts_at' => now()->addDays(2)->format('Y-m-d 10:00'), 'ends_at' => now()->addDays(5)->format('Y-m-d 10:00'),
        ]))->assertOk()->json();
    }

    public function test_waiver_option_is_created_switched_off(): void
    {
        $waiver = Extra::query()->where('slug', 'bez-zaloga')->sole();

        $this->assertTrue($waiver->waives_deposit);
        $this->assertFalse($waiver->is_active);
        $this->get('/avto/kia-rio')->assertOk()->assertDontSee('можно без залога');
    }

    public function test_waiver_removes_deposit_and_adds_daily_fee(): void
    {
        $waiver = $this->waiver();

        $this->assertSame(5000, $this->quote()['deposit']);
        $quote = $this->quote([$waiver->id]);
        $this->assertSame(0, $quote['deposit']);
        $this->assertTrue($quote['deposit_waived']);
        $this->assertSame(6000 + 400 * 3, $quote['total']);
        $this->assertStringContainsString('Без залога.', $quote['human']);
        $this->get('/avto/kia-rio')->assertOk()->assertSee('можно без залога: +400 ₽/сут');
    }

    public function test_inactive_waiver_cannot_be_applied(): void
    {
        $waiver = $this->waiver(active: false);

        $this->assertSame(5000, $this->quote([$waiver->id])['deposit']);
    }

    public function test_client_chooses_waiver_on_second_step(): void
    {
        $waiver = $this->waiver();
        $this->post('/zayavka', [
            'car_id' => $this->car->id, 'phone' => '+79780000000', 'pd_consent' => '1',
            'starts_at' => now()->addDays(2)->format('Y-m-d\T10:00'), 'ends_at' => now()->addDays(5)->format('Y-m-d\T10:00'),
        ]);
        $booking = Booking::query()->sole();

        $this->post(URL::signedRoute('booking.details', $booking), ['extras' => [$waiver->id]])->assertRedirect();

        $booking->refresh();
        $this->assertSame(7200, $booking->total);
        $this->assertTrue($booking->quote_snapshot['deposit_waived']);
        $this->get($booking->statusUrl())->assertOk()->assertSee('без залога');
    }
}
