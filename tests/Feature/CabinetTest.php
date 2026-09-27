<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Brand;
use App\Models\Car;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CabinetTest extends TestCase
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

    private function booking(string $phone, array $attributes = []): Booking
    {
        return Booking::query()->create([
            'car_id' => $this->car->id, 'phone' => $phone, 'status' => 'confirmed', 'total' => 6000, 'source' => 'card',
            'starts_at' => Carbon::parse('+2 days 10:00'), 'ends_at' => Carbon::parse('+5 days 10:00'),
            ...$attributes,
        ]);
    }

    private function enableSms(): void
    {
        Setting::put('sms_enabled', true);
        Setting::putSecret('sms_api_key', 'key');
        Http::fake(['sms.ru/*' => Http::response(['status' => 'OK', 'sms' => ['79780000000' => ['status' => 'OK']]])]);
    }

    public function test_guest_sees_sms_login_form_only_when_sms_are_on(): void
    {
        $this->get('/kabinet')->assertOk()->assertSee('Все мои брони')->assertDontSee('Получить код в SMS');

        $this->enableSms();
        $this->get('/kabinet')->assertOk()->assertSee('Телефон, на который оформляли заявку')->assertSee('Получить код в SMS');
    }

    public function test_login_by_sms_code_shows_only_own_bookings_in_any_phone_format(): void
    {
        $this->enableSms();
        $mine = $this->booking('+7 (978) 000-00-00');
        $mineOld = $this->booking('89780000000', ['starts_at' => now()->subDays(10), 'ends_at' => now()->subDays(7)]);
        $foreign = $this->booking('+79781112233');

        $this->post('/kabinet/kod', ['phone' => '8 978 000 00 00'])->assertRedirect('/kabinet');
        $code = null;
        Http::assertSent(function (Request $r) use (&$code) {
            preg_match('/\d{6}/', $r['msg'], $m);
            $code = $m[0] ?? null;

            return $r['to'] === '79780000000';
        });

        $this->post('/kabinet/vhod', ['phone' => '79780000000', 'code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/kabinet/vhod', ['phone' => '79780000000', 'code' => $code])->assertRedirect('/kabinet');

        $this->get('/kabinet')->assertOk()
            ->assertSee('Заявка №'.$mine->id)->assertSee('Заявка №'.$mineOld->id)->assertSee('Прошлые')
            ->assertDontSee('Заявка №'.$foreign->id);
    }

    public function test_code_expires_after_too_many_wrong_attempts(): void
    {
        $this->enableSms();
        $this->post('/kabinet/kod', ['phone' => '+79780000000']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/kabinet/vhod', ['phone' => '79780000000', 'code' => '111111']);
        }

        $this->post('/kabinet/vhod', ['phone' => '79780000000', 'code' => '111111'])->assertSessionHasErrors(['phone' => 'Код устарел. Запросите новый.']);
    }

    public function test_without_sms_login_goes_through_booking_page(): void
    {
        $booking = $this->booking('+79780000000');

        $this->post('/kabinet/kod', ['phone' => '+79780000000'])->assertSessionHasErrors('phone');
        $this->get($booking->statusUrl())->assertSee('Все мои брони');
        $this->post('/b/'.$booking->public_token.'/kabinet')->assertRedirect('/kabinet');

        $this->get('/kabinet')->assertSee('Заявка №'.$booking->id)->assertSee('Продлить аренду');
    }

    public function test_extension_request_counts_extra_and_notifies_manager(): void
    {
        Setting::put('telegram_bot_token', 'bot');
        Setting::put('telegram_chat_id', '-1');
        Http::fake();
        $booking = $this->booking('+79780000000');
        $this->post('/b/'.$booking->public_token.'/kabinet');

        $this->post('/kabinet/'.$booking->id.'/prodlit', ['ends_at' => $booking->ends_at->copy()->addDays(2)->format('Y-m-d\TH:i')])
            ->assertSessionHas('extended_'.$booking->id);

        Http::assertSent(fn (Request $r) => str_contains($r['text'], 'Продление, заявка №'.$booking->id) && str_contains($r['text'], '4 000 ₽'));
        $this->assertStringContainsString('просит продлить', $booking->events()->where('type', 'note')->sole()->comment);
    }

    public function test_extension_is_refused_when_car_is_taken_right_after(): void
    {
        $booking = $this->booking('+79780000000');
        $this->booking('+79781112233', ['starts_at' => $booking->ends_at->copy()->addDay(), 'ends_at' => $booking->ends_at->copy()->addDays(4)]);
        $this->post('/b/'.$booking->public_token.'/kabinet');

        $this->post('/kabinet/'.$booking->id.'/prodlit', ['ends_at' => $booking->ends_at->copy()->addDays(3)->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors('ends_at_'.$booking->id);
    }

    public function test_cannot_extend_someone_elses_booking(): void
    {
        $mine = $this->booking('+79780000000');
        $foreign = $this->booking('+79781112233');
        $this->post('/b/'.$mine->public_token.'/kabinet');

        $this->post('/kabinet/'.$foreign->id.'/prodlit', ['ends_at' => now()->addDays(9)->format('Y-m-d\TH:i')])->assertNotFound();
    }
}
