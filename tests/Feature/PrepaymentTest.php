<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Brand;
use App\Models\Car;
use App\Models\Payment;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PrepaymentTest extends TestCase
{
    use RefreshDatabase;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::put('payments_enabled', true);
        Setting::put('yookassa_shop_id', '123456');
        Setting::putSecret('yookassa_secret_key', 'live_secret');
        Setting::put('prepay_mode', 'percent');
        Setting::put('prepay_value', 15);

        $brand = Brand::query()->create(['slug' => 'kia', 'name' => 'Kia']);
        $car = Car::query()->create(['brand_id' => $brand->id, 'name' => 'Kia Rio', 'slug' => 'kia-rio', 'gearbox' => 'at', 'seats' => 5, 'status' => 'published', 'min_days' => 1]);
        $this->booking = Booking::query()->create([
            'car_id' => $car->id, 'phone' => '+79780000000', 'status' => 'confirmed', 'total' => 10000, 'source' => 'card',
            'starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(6), 'quote_snapshot' => ['days' => 3],
        ]);
    }

    private function fakeYooKassa(string $status = 'pending'): void
    {
        Http::fake([
            'api.yookassa.ru/v3/payments' => Http::response(['id' => 'pay-1', 'status' => 'pending', 'confirmation' => ['type' => 'redirect', 'confirmation_url' => 'https://yoomoney.ru/checkout/pay-1']]),
            'api.yookassa.ru/v3/payments/pay-1' => Http::response(['id' => 'pay-1', 'status' => $status, 'payment_method' => ['type' => 'sbp']]),
        ]);
    }

    public function test_confirmed_booking_offers_prepayment_and_redirects_to_yookassa(): void
    {
        $this->fakeYooKassa();

        $this->get($this->booking->statusUrl())->assertOk()->assertSee('Внести предоплату 1 500 ₽');
        $this->post(route('booking.pay', $this->booking->public_token))->assertRedirect('https://yoomoney.ru/checkout/pay-1');

        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.yookassa.ru/v3/payments'
            && $r['amount'] === ['value' => '1500.00', 'currency' => 'RUB'] && $r['capture'] === true
            && $r['confirmation']['return_url'] === $this->booking->statusUrl()
            && $r->hasHeader('Idempotence-Key') && $r['metadata']['booking_id'] === (string) $this->booking->id);
        $this->assertSame('pay-1', Payment::query()->sole()->provider_id);
    }

    public function test_new_booking_without_confirmation_gets_no_payment_button(): void
    {
        $this->booking->update(['status' => 'new']);

        $this->get($this->booking->statusUrl())->assertOk()->assertDontSee('Внести предоплату');
        $this->post(route('booking.pay', $this->booking->public_token))->assertRedirect($this->booking->statusUrl());
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_webhook_marks_booking_prepaid_only_after_checking_with_yookassa_and_only_once(): void
    {
        $this->fakeYooKassa(status: 'succeeded');
        $this->post(route('booking.pay', $this->booking->public_token));

        // Подделанное тело «succeeded» не важно: статус спрашиваем у ЮKassa
        $payload = ['type' => 'notification', 'event' => 'payment.succeeded', 'object' => ['id' => 'pay-1', 'status' => 'succeeded']];
        $this->postJson('/integrations/yookassa/webhook', $payload)->assertOk();
        $this->postJson('/integrations/yookassa/webhook', $payload)->assertOk();

        $booking = $this->booking->fresh();
        $this->assertSame(1500, $booking->prepaid_amount);
        $this->assertNotNull($booking->prepaid_at);
        $this->assertSame('sbp', Payment::query()->sole()->method);
        $this->assertSame(1, $booking->events()->where('type', 'payment')->count());
        $this->get($booking->statusUrl())->assertSee('Предоплата получена')->assertSee('При получении');
    }

    public function test_return_from_payment_page_updates_status_without_waiting_for_webhook(): void
    {
        $this->fakeYooKassa(status: 'succeeded');
        $this->post(route('booking.pay', $this->booking->public_token));

        $this->get($this->booking->statusUrl())->assertOk()->assertSee('Предоплата получена — 1 500 ₽');
    }

    public function test_first_day_mode_and_receipt(): void
    {
        Setting::put('prepay_mode', 'first_day');
        Setting::put('yookassa_receipt', true);
        Setting::put('yookassa_vat_code', 1);
        $this->fakeYooKassa();

        $this->post(route('booking.pay', $this->booking->public_token));

        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.yookassa.ru/v3/payments'
            && $r['amount']['value'] === '3334.00'
            && $r['receipt']['customer']['phone'] === '79780000000'
            && $r['receipt']['items'][0]['vat_code'] === 1 && $r['receipt']['items'][0]['payment_mode'] === 'advance');
    }

    public function test_unknown_payment_in_webhook_is_ignored(): void
    {
        Http::fake();

        $this->postJson('/integrations/yookassa/webhook', ['object' => ['id' => 'nope']])->assertOk();

        Http::assertNothingSent();
    }
}
