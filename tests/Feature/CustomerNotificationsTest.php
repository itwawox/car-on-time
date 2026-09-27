<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Brand;
use App\Models\Car;
use App\Models\IntegrationLog;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CustomerNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private Car $car;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::put('sms_enabled', true);
        Setting::put('sms_provider', 'smsru');
        Setting::putSecret('sms_api_key', 'api-key');
        Setting::put('brand_name', 'Car on Time');

        $brand = Brand::query()->create(['slug' => 'kia', 'name' => 'Kia']);
        $this->car = Car::query()->create(['brand_id' => $brand->id, 'name' => 'Kia Rio', 'slug' => 'kia-rio', 'gearbox' => 'at', 'seats' => 5, 'status' => 'published', 'min_days' => 1]);
    }

    private function fakeSmsRu(string $status = 'OK'): void
    {
        Http::fake(['sms.ru/*' => Http::response(['status' => 'OK', 'sms' => ['79780000000' => ['status' => $status, 'status_text' => 'Неверный номер']]])]);
    }

    private function booking(array $attributes = []): Booking
    {
        return Booking::query()->create([
            'car_id' => $this->car->id, 'phone' => '8 978 000-00-00', 'status' => 'new', 'total' => 6000, 'source' => 'card',
            'starts_at' => Carbon::parse('+2 days 10:00'), 'ends_at' => Carbon::parse('+5 days 10:00'),
            ...$attributes,
        ]);
    }

    public function test_new_booking_gets_sms_with_short_status_link(): void
    {
        $this->fakeSmsRu();

        $booking = $this->booking();

        Http::assertSent(fn (Request $r) => $r['to'] === '79780000000' && $r['api_id'] === 'api-key'
            && str_contains($r['msg'], 'заявка №'.$booking->id.' принята')
            && str_contains($r['msg'], '/b/'.$booking->public_token));
        $this->assertSame('success', IntegrationLog::query()->where('integration', 'sms')->sole()->status);
        $this->assertSame(1, $booking->events()->where('type', 'notified')->count());
    }

    public function test_confirmation_sms_uses_template_from_admin(): void
    {
        Setting::put('sms_tpl_confirmed', 'Ура! {car} ждёт вас {date} в {time}.');
        $this->fakeSmsRu();
        $booking = $this->booking();

        $booking->update(['status' => 'confirmed']);

        Http::assertSent(fn (Request $r) => $r['msg'] === 'Ура! Kia Rio ждёт вас '.$booking->starts_at->translatedFormat('j F').' в 10:00.');
    }

    public function test_nothing_is_sent_when_sms_are_off_or_event_is_disabled(): void
    {
        Http::fake();
        Setting::put('sms_events', ['confirmed']);
        $this->booking();

        Setting::put('sms_enabled', false);
        $this->booking()->update(['status' => 'confirmed']);

        Http::assertNothingSent();
    }

    public function test_provider_rejection_is_logged_and_booking_is_kept(): void
    {
        $this->fakeSmsRu(status: 'ERROR');

        $booking = $this->booking();

        $this->assertModelExists($booking);
        $log = IntegrationLog::query()->sole();
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('Неверный номер', $log->error);
    }

    public function test_status_page_by_short_link_follows_booking_stage(): void
    {
        Http::fake();
        $booking = $this->booking();

        $this->get('/b/'.$booking->public_token)->assertOk()->assertSee('Заявка №'.$booking->id)->assertSee('Заявка принята');

        $booking->update(['status' => 'confirmed']);
        $this->get('/b/'.$booking->public_token)->assertOk()->assertSee('Бронь подтверждена')->assertSee('aria-current="step"', false);

        $booking->update(['status' => 'declined']);
        $this->get('/b/'.$booking->public_token)->assertOk()->assertSee('Машина недоступна');

        $this->get('/b/aaaaaaaaaaaa')->assertNotFound();
    }

    public function test_reminder_and_review_request_are_sent_once(): void
    {
        $this->fakeSmsRu();
        $tomorrow = $this->booking(['status' => 'confirmed', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(3)]);
        $returned = $this->booking(['status' => 'confirmed', 'starts_at' => now()->subDays(3), 'ends_at' => now()->subHours(5)]);
        Http::fake(['sms.ru/*' => Http::response(['status' => 'OK', 'sms' => ['79780000000' => ['status' => 'OK']]])]);

        $this->artisan('bookings:remind')->assertSuccessful();
        $this->artisan('bookings:remind')->assertSuccessful();

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => str_contains($r['msg'], 'Напоминаем: завтра'));
        Http::assertSent(fn (Request $r) => str_contains($r['msg'], 'Расскажите, как прошла поездка'));
        $this->assertNotNull($tomorrow->fresh()->reminded_at);
        $this->assertNotNull($returned->fresh()->review_requested_at);
    }

    public function test_smsc_provider_is_supported(): void
    {
        Setting::put('sms_provider', 'smsc');
        Setting::put('sms_login', 'caron');
        Setting::putSecret('sms_password', 'pwd');
        Http::fake(['smsc.ru/*' => Http::response(['id' => 42, 'cnt' => 1])]);

        $this->booking();

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'smsc.ru') && $r['login'] === 'caron' && $r['psw'] === 'pwd' && $r['phones'] === '79780000000');
    }
}
