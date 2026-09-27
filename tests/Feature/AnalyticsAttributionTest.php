<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Brand;
use App\Models\Car;
use App\Models\Lead;
use App\Models\Setting;
use App\Services\TelegramNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnalyticsAttributionTest extends TestCase
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

    private function book(array $extra = []): Booking
    {
        $this->post('/zayavka', [
            'car_id' => $this->car->id, 'phone' => '+79780000000', 'pd_consent' => '1',
            'starts_at' => now()->addDays(2)->format('Y-m-d\T10:00'), 'ends_at' => now()->addDays(5)->format('Y-m-d\T10:00'),
            ...$extra,
        ])->assertRedirect();

        return Booking::query()->sole();
    }

    public function test_booking_keeps_only_known_attribution_keys(): void
    {
        $booking = $this->book(['utm' => json_encode([
            'utm_source' => 'yandex', 'utm_medium' => 'cpc', 'utm_campaign' => '<b>leto</b>', 'evil' => 'x', 'landing' => '/avto/kia-rio?utm_source=yandex',
        ])]);

        $this->assertSame(['utm_source' => 'yandex', 'utm_medium' => 'cpc', 'utm_campaign' => 'leto', 'landing' => '/avto/kia-rio?utm_source=yandex'], $booking->utm);
        $this->assertStringContainsString('Трафик: yandex / cpc / leto', app(TelegramNotifier::class)->bookingText($booking));
    }

    public function test_broken_attribution_is_ignored(): void
    {
        $this->assertNull($this->book(['utm' => '{not json'])->utm);
    }

    public function test_callback_lead_keeps_attribution(): void
    {
        $this->post('/obratnyj-zvonok', [
            'type' => 'callback', 'phone' => '+79780000001', 'pd_consent' => '1',
            'utm' => json_encode(['yclid' => '123', 'referrer' => 'https://yandex.ru/']),
        ]);

        $this->assertSame(['yclid' => '123', 'referrer' => 'https://yandex.ru/'], Lead::query()->sole()->utm);
    }

    public function test_utm_goes_to_standard_bitrix24_fields(): void
    {
        $hook = 'https://portal.test/rest/1/token/';
        Setting::put('bitrix_enabled', true);
        Setting::putSecret('bitrix_webhook_url', $hook);
        Http::fake([
            $hook.'crm.duplicate.findbycomm.json' => Http::response(['result' => []]),
            $hook.'crm.contact.add.json' => Http::response(['result' => 1]),
            $hook.'crm.deal.add.json' => Http::response(['result' => 2]),
        ]);

        $this->book(['utm' => json_encode(['utm_source' => 'yandex', 'utm_campaign' => 'leto'])]);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'crm.deal.add.json')
            && $r['fields']['UTM_SOURCE'] === 'yandex' && $r['fields']['UTM_CAMPAIGN'] === 'leto');
    }

    public function test_pages_expose_data_for_metrika_ecommerce(): void
    {
        $this->get('/avto/kia-rio')->assertOk()->assertSee('data-analytics-car=', false)->assertSee('Kia Rio');

        $response = $this->book();
        $this->get('/b/'.$response->public_token)->assertOk()->assertSee('data-analytics-purchase=', false);
    }
}
