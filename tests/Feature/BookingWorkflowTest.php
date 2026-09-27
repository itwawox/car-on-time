<?php

namespace Tests\Feature;

use App\Filament\Resources\Bookings\Pages\EditBooking;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\Bookings\RelationManagers\EventsRelationManager;
use App\Models\Booking;
use App\Models\Brand;
use App\Models\Car;
use App\Models\CarBlock;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class BookingWorkflowTest extends TestCase
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

    private function booking(string $start = '+2 days 10:00', string $end = '+5 days 10:00', string $status = 'new'): Booking
    {
        return Booking::query()->create([
            'car_id' => $this->car->id, 'phone' => '+79780000000', 'status' => $status, 'total' => 6000, 'source' => 'card',
            'starts_at' => Carbon::parse($start), 'ends_at' => Carbon::parse($end),
        ]);
    }

    public function test_confirmed_booking_occupies_car_and_decline_frees_it(): void
    {
        $booking = $this->booking();

        $booking->update(['status' => 'confirmed']);
        $block = CarBlock::query()->sole();
        $this->assertSame($booking->id, $block->booking_id);
        $this->assertNotNull($booking->fresh()->confirmed_at);

        $booking->update(['status' => 'declined']);
        $this->assertSame(0, CarBlock::query()->count());
    }

    public function test_quote_and_catalog_prices_report_busy_car_including_prep_buffer(): void
    {
        Setting::put('availability_buffer_hours', 3);
        $this->booking('+2 days 10:00', '+5 days 10:00', 'confirmed');

        // Выдача через час после возврата — меньше буфера, машина ещё занята
        $busy = $this->getJson('/quote?'.http_build_query(['car_id' => $this->car->id, 'starts_at' => now()->addDays(5)->format('Y-m-d 11:00'), 'ends_at' => now()->addDays(7)->format('Y-m-d 11:00')]));
        $busy->assertOk()->assertJsonPath('available', false);

        $free = $this->getJson('/quote?'.http_build_query(['car_id' => $this->car->id, 'starts_at' => now()->addDays(5)->format('Y-m-d 14:00'), 'ends_at' => now()->addDays(7)->format('Y-m-d 14:00')]));
        $free->assertOk()->assertJsonPath('available', true);

        $this->getJson('/quote/batch?'.http_build_query(['ids' => (string) $this->car->id, 'starts_at' => now()->addDays(3)->format('Y-m-d 10:00'), 'ends_at' => now()->addDays(4)->format('Y-m-d 10:00')]))
            ->assertOk()->assertJsonPath('prices.'.$this->car->id.'.available', false);
    }

    public function test_status_changes_are_recorded_with_author_and_first_response_time(): void
    {
        $manager = User::factory()->role('manager')->create();
        $booking = $this->booking();

        $this->actingAs($manager);
        $booking->update(['status' => 'checking']);

        $this->assertNotNull($booking->fresh()->first_response_at);
        $event = $booking->events()->where('type', 'status')->sole();
        $this->assertSame(['new', 'checking', $manager->id], [$event->from, $event->to, $event->user_id]);
        $this->assertSame(1, $booking->events()->where('type', 'created')->count());
    }

    public function test_overdue_booking_is_escalated_to_work_chat_once(): void
    {
        Setting::put('telegram_bot_token', 'bot-token');
        Setting::put('telegram_chat_id', '-100');
        Setting::put('booking_sla_minutes', 15);
        Http::fake();

        $this->travel(-20)->minutes();
        $overdue = $this->booking();
        $this->travelBack();
        $fresh = $this->booking();

        $this->artisan('bookings:escalate')->assertSuccessful();
        $this->artisan('bookings:escalate')->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => str_contains($r['text'], 'Заявка №'.$overdue->id.' ждёт ответа'));
        $this->assertNotNull($overdue->fresh()->escalated_at);
        $this->assertNull($fresh->fresh()->escalated_at);
    }

    public function test_manager_cannot_confirm_booking_on_occupied_car(): void
    {
        $this->booking('+2 days 10:00', '+5 days 10:00', 'confirmed');
        $overlapping = $this->booking('+4 days 10:00', '+6 days 10:00');
        $this->actingAs(User::factory()->role('manager')->create());

        Livewire::test(ListBookings::class)
            ->callTableAction('status_confirmed', $overlapping)
            ->assertNotified('Машина занята на эти даты');

        $this->assertSame('new', $overlapping->fresh()->status);
    }

    public function test_admin_booking_screens_render(): void
    {
        $booking = $this->booking();
        $this->booking('+2 days 10:00', '+5 days 10:00', 'confirmed');
        $this->actingAs(User::factory()->role('manager')->create());

        $this->get('/admin/bookings')->assertOk()->assertSee('Новые')->assertSee('+79780000000');
        $this->get('/admin/bookings/'.$booking->id.'/edit')->assertOk()->assertSee('Заявка №'.$booking->id);
        Livewire::test(EventsRelationManager::class, ['ownerRecord' => $booking, 'pageClass' => EditBooking::class])
            ->loadTable()->assertCanSeeTableRecords($booking->events)->assertSee('Заявка получена');
        $this->get('/admin/fleet-calendar')->assertOk()->assertSee('Kia Rio')->assertSee('Загрузка');
    }
}
