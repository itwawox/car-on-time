<?php

namespace Tests\Feature;

use App\Filament\Widgets\BookingsChart;
use App\Filament\Widgets\KpiOverview;
use App\Filament\Widgets\PartnerRevenue;
use App\Filament\Widgets\ZeroResultSearches;
use App\Models\Booking;
use App\Models\Brand;
use App\Models\Car;
use App\Models\Partner;
use App\Models\User;
use App\Support\Kpi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function booking(Car $car, string $status, int $total, ?int $respondedAfter = null): Booking
    {
        $this->travel(-2)->hours();
        $booking = Booking::query()->create([
            'car_id' => $car->id, 'partner_id' => $car->partner_id, 'phone' => '+79780000000', 'status' => 'new', 'total' => $total, 'source' => 'card',
            'starts_at' => now()->addDays(1), 'ends_at' => now()->addDays(3),
        ]);
        $this->travelBack();
        if ($respondedAfter !== null) {
            $booking->forceFill(['first_response_at' => $booking->created_at->copy()->addMinutes($respondedAfter)])->saveQuietly();
        }
        if ($status !== 'new') {
            $booking->update(['status' => $status]);
        }

        return $booking;
    }

    public function test_kpis_are_computed_from_bookings(): void
    {
        $partner = Partner::query()->create(['name' => 'Иван', 'commission_percent' => 20, 'is_active' => true]);
        $brand = Brand::query()->create(['slug' => 'kia', 'name' => 'Kia']);
        $car = Car::query()->create(['brand_id' => $brand->id, 'partner_id' => $partner->id, 'name' => 'Kia Rio', 'slug' => 'kia-rio', 'gearbox' => 'at', 'seats' => 5, 'status' => 'published', 'min_days' => 1]);

        $this->booking($car, 'confirmed', 6000, respondedAfter: 5);
        $this->booking($car, 'confirmed', 4000, respondedAfter: 10);
        $this->booking($car, 'declined', 3000, respondedAfter: 40);
        $this->booking($car, 'new', 2000);

        $kpi = Kpi::lastDays(30);
        $this->assertSame(4, $kpi->received());
        $this->assertSame(50.0, $kpi->conversion());
        $this->assertSame(10, $kpi->medianResponseMinutes());
        $this->assertSame(66.7, $kpi->slaHitRate());
        $this->assertSame(10000, $kpi->revenue());
        $this->assertSame([['partner' => 'Иван', 'bookings' => 2, 'revenue' => 10000, 'commission' => 2000]], $kpi->partners()->all());
        $this->assertGreaterThan(0, Kpi::utilizationAhead(7));
    }

    public function test_dashboard_shows_widgets_by_role(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/admin')->assertOk();
        Livewire::test(KpiOverview::class)->assertSee('Ждут ответа сейчас')->assertSee('Конверсия в аренду');
        Livewire::test(PartnerRevenue::class)->assertSee('Партнёры: выручка и комиссия');
        Livewire::test(BookingsChart::class)->assertOk();
        Livewire::test(ZeroResultSearches::class)->assertOk();

        $this->actingAs(User::factory()->role('manager')->create());
        $this->assertTrue(KpiOverview::canView());
        $this->assertFalse(PartnerRevenue::canView());

        $this->actingAs(User::factory()->role('editor')->create());
        $this->assertFalse(KpiOverview::canView());
        $this->assertTrue(ZeroResultSearches::canView());
    }
}
