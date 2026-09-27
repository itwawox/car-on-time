<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Brand;
use App\Models\Car;
use App\Models\Extra;
use App\Models\Location;
use App\Services\TelegramNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class BookingUxTest extends TestCase
{
    use RefreshDatabase;

    private function car(): Car
    {
        $brand = Brand::query()->firstOrCreate(['slug' => 'kia'], ['name' => 'Kia']);
        if ($existing = Car::query()->where('slug', 'kia-rio')->first()) {
            return $existing;
        }
        $car = Car::query()->create(['brand_id' => $brand->id, 'name' => 'Kia Rio', 'slug' => 'kia-rio', 'gearbox' => 'at', 'seats' => 5, 'status' => 'published', 'min_days' => 1]);
        $car->prices()->create(['price' => 2000, 'days_from' => 1, 'days_to' => null]);

        return $car;
    }

    private function book(array $extra = [])
    {
        $car = $this->car();
        $location = Location::query()->firstOrCreate(['slug' => 'office'], ['name' => 'Офис', 'type' => 'office', 'is_active' => true]);

        return $this->post('/zayavka', [
            'car_id' => $car->id, 'phone' => '+7 (978) 948-48-48',
            'starts_at' => now()->addDays(2)->format('Y-m-d\T10:00'), 'ends_at' => now()->addDays(5)->format('Y-m-d\T10:00'),
            'pickup_location_id' => $location->id, 'return_location_id' => $location->id, 'pd_consent' => '1',
            ...$extra,
        ]);
    }

    public function test_thanks_page_requires_signed_link(): void
    {
        $response = $this->book();
        $booking = Booking::query()->sole();

        $response->assertRedirect();
        $this->assertStringContainsString('signature=', $response->headers->get('Location'));
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('Заявка №'.$booking->id)->assertSee('Что дальше');

        // Перебор номера заявки без подписи не показывает чужие данные
        $this->get('/zayavka/'.$booking->id.'/spasibo')->assertForbidden();
    }

    public function test_extras_and_promo_code_are_saved_and_sent_to_telegram_text(): void
    {
        $seat = Extra::query()->create(['name' => 'Детское кресло', 'slug' => 'seat', 'price_per_day' => 0, 'is_free' => true, 'sort' => 1]);

        $this->book(['extras' => [$seat->id], 'promo_code' => ' summer26 ']);
        $booking = Booking::query()->sole();

        $this->assertSame('SUMMER26', $booking->promo_code);
        $this->assertSame('Детское кресло', $booking->extras[0]['name']);
        $text = app(TelegramNotifier::class)->bookingText($booking);
        $this->assertStringContainsString('Доп. услуги: Детское кресло', $text);
        $this->assertStringContainsString('Промокод: SUMMER26', $text);
    }

    public function test_calendar_file_is_signed_and_valid(): void
    {
        $this->book();
        $booking = Booking::query()->sole();

        $this->get('/zayavka/'.$booking->id.'/calendar.ics')->assertForbidden();
        $ics = $this->get(URL::signedRoute('booking.calendar', $booking))->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')->getContent();
        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        $this->assertStringContainsString('SUMMARY:Получение машины: Kia Rio', $ics);
    }

    public function test_missing_phone_shows_human_error(): void
    {
        $this->book(['phone' => ''])->assertSessionHasErrors(['phone' => 'Укажите телефон — по нему подтвердим наличие машины.']);
        $this->assertSame(0, Booking::query()->count());
    }

    public function test_booking_requires_separate_pd_consent_and_stores_its_time(): void
    {
        $this->book(['pd_consent' => null])->assertSessionHasErrors('pd_consent');
        $this->assertSame(0, Booking::query()->count());

        $this->book();
        $this->assertNotNull(Booking::query()->sole()->consent_at);
    }

    public function test_car_page_form_asks_only_dates_place_and_phone(): void
    {
        $this->car();
        Extra::query()->create(['name' => 'Бустер', 'slug' => 'booster', 'price_per_day' => 0, 'is_free' => true, 'sort' => 1]);

        $html = $this->get('/avto/kia-rio')->assertOk()
            ->assertSee('data-phone-mask', false)->assertSee('name="pd_consent"', false)->assertSee('Вернуть в другом месте')
            ->getContent();
        $form = substr($html, strpos($html, 'data-quote-form'), strpos($html, '</form>', strpos($html, 'data-quote-form')) - strpos($html, 'data-quote-form'));

        foreach (['name="customer_name"', 'name="extras[]"', 'name="promo_code"', 'name="whatsapp"'] as $field) {
            $this->assertStringNotContainsString($field, $form);
        }
    }

    public function test_minimal_booking_is_accepted_and_return_defaults_to_pickup(): void
    {
        $car = $this->car();
        $location = Location::query()->firstOrCreate(['slug' => 'office'], ['name' => 'Офис', 'type' => 'office', 'is_active' => true]);

        $this->post('/zayavka', [
            'car_id' => $car->id, 'phone' => '+7 (978) 948-48-48', 'pickup_location_id' => $location->id, 'pd_consent' => '1',
            'starts_at' => now()->addDays(2)->format('Y-m-d\T10:00'), 'ends_at' => now()->addDays(5)->format('Y-m-d\T10:00'),
        ])->assertRedirect();

        $booking = Booking::query()->sole();
        $this->assertSame($location->id, $booking->pickup_location_id);
        $this->assertSame($location->id, $booking->return_location_id);
        $this->assertSame(6000, $booking->total);
    }

    public function test_thanks_page_offers_optional_details_step(): void
    {
        Extra::query()->create(['name' => 'Бустер', 'slug' => 'booster', 'price_per_day' => 0, 'is_free' => true, 'sort' => 1]);
        $response = $this->book();

        $this->get($response->headers->get('Location'))->assertOk()
            ->assertSee('Уточните детали')->assertSee('name="customer_name"', false)
            ->assertSee('name="extras[]"', false)->assertSee('name="promo_code"', false)->assertSee('Бустер');
    }

    public function test_details_require_signed_link(): void
    {
        $this->book();
        $booking = Booking::query()->sole();

        $this->post('/zayavka/'.$booking->id.'/detali', ['customer_name' => 'Хакер'])->assertForbidden();
        $this->assertNull($booking->fresh()->customer_name);
    }

    public function test_details_are_saved_and_extras_recalculate_total(): void
    {
        $seat = Extra::query()->create(['name' => 'Детское кресло', 'slug' => 'seat', 'price_per_day' => 300, 'is_free' => false, 'sort' => 1]);
        $this->book();
        $booking = Booking::query()->sole();
        $this->assertSame(6000, $booking->total);

        $this->post(URL::signedRoute('booking.details', $booking), [
            'customer_name' => ' Анна ', 'contact' => ['whatsapp', 'call'], 'extras' => [$seat->id], 'promo_code' => 'nope',
        ])->assertRedirect();

        $booking->refresh();
        $this->assertSame('Анна', $booking->customer_name);
        $this->assertTrue($booking->whatsapp);
        $this->assertFalse($booking->telegram);
        $this->assertSame('NOPE', $booking->promo_code);
        $this->assertSame('Детское кресло', $booking->extras[0]['name']);
        $this->assertSame(6000 + 300 * 3, $booking->total);
    }
}
