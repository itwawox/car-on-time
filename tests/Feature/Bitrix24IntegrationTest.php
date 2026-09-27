<?php

namespace Tests\Feature;

use App\Filament\Pages\Integrations;
use App\Models\Booking;
use App\Models\Brand;
use App\Models\Car;
use App\Models\IntegrationLog;
use App\Models\Lead;
use App\Models\Location;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class Bitrix24IntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const HOOK = 'https://portal.test/rest/1/secret-token/';

    protected function setUp(): void
    {
        parent::setUp();

        Setting::put('bitrix_enabled', true);
        Setting::putSecret('bitrix_webhook_url', self::HOOK);
        Setting::put('bitrix_booking_entity', 'deal');
        Setting::put('bitrix_category_id', 3);
        Setting::put('bitrix_assigned_by_id', 9);
        Setting::put('bitrix_stage_map', ['new' => 'C3:NEW', 'confirmed' => 'C3:WON', 'declined' => 'C3:LOSE']);
        Setting::put('bitrix_booking_field_map', [['site' => 'car', 'crm' => 'UF_CRM_CAR'], ['site' => 'promo_code', 'crm' => 'UF_CRM_PROMO']]);
    }

    private function fakePortal(array $contacts = []): void
    {
        Http::fake([
            self::HOOK.'crm.duplicate.findbycomm.json' => Http::response(['result' => $contacts ? ['CONTACT' => $contacts] : []]),
            self::HOOK.'crm.contact.add.json' => Http::response(['result' => 11]),
            self::HOOK.'crm.deal.add.json' => Http::response(['result' => 55]),
            self::HOOK.'crm.deal.update.json' => Http::response(['result' => true]),
            self::HOOK.'crm.lead.add.json' => Http::response(['result' => 77]),
            self::HOOK.'crm.deal.get.json' => Http::response(['result' => ['ID' => 55, 'STAGE_ID' => 'C3:WON']]),
        ]);
    }

    private function book(array $extra = []): Booking
    {
        $brand = Brand::query()->create(['slug' => 'kia', 'name' => 'Kia']);
        $car = Car::query()->create(['brand_id' => $brand->id, 'name' => 'Kia Rio', 'slug' => 'kia-rio', 'gearbox' => 'at', 'seats' => 5, 'status' => 'published', 'min_days' => 1]);
        $car->prices()->create(['price' => 2000, 'days_from' => 1, 'days_to' => null]);
        $location = Location::query()->create(['slug' => 'office', 'name' => 'Офис', 'type' => 'office', 'is_active' => true]);

        $this->post('/zayavka', [
            'car_id' => $car->id, 'phone' => '8 (978) 948-48-48', 'customer_name' => 'Иван',
            'starts_at' => now()->addDays(2)->format('Y-m-d\T10:00'), 'ends_at' => now()->addDays(5)->format('Y-m-d\T10:00'),
            'pickup_location_id' => $location->id, 'return_location_id' => $location->id, 'pd_consent' => '1',
            ...$extra,
        ])->assertRedirect();

        return Booking::query()->sole();
    }

    public function test_new_booking_becomes_deal_with_new_contact_and_mapped_fields(): void
    {
        $this->fakePortal();

        $booking = $this->book(['promo_code' => 'summer']);

        $this->assertSame('deal', $booking->crm_entity);
        $this->assertSame('55', $booking->crm_id);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'crm.contact.add.json')
            && $r['fields']['PHONE'][0]['VALUE'] === '+79789484848' && $r['fields']['NAME'] === 'Иван');
        Http::assertSent(function (Request $r) {
            if (! str_ends_with($r->url(), 'crm.deal.add.json')) {
                return false;
            }
            $f = $r['fields'];

            return $f['CATEGORY_ID'] === 3 && $f['STAGE_ID'] === 'C3:NEW' && $f['CONTACT_ID'] === 11
                && $f['ASSIGNED_BY_ID'] === 9 && $f['OPPORTUNITY'] === 6000
                && $f['UF_CRM_CAR'] === 'Kia Rio' && $f['UF_CRM_PROMO'] === 'SUMMER'
                && $f['ORIGIN_ID'] === 'booking-1';
        });
        $this->assertDatabaseHas('integration_logs', ['event' => 'booking.created', 'status' => 'success', 'subject_id' => $booking->id]);
    }

    public function test_existing_contact_is_reused_instead_of_duplicated(): void
    {
        $this->fakePortal(contacts: [7]);

        $this->book();

        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), 'crm.contact.add.json'));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'crm.deal.add.json') && $r['fields']['CONTACT_ID'] === 7);
    }

    public function test_status_change_moves_deal_to_mapped_stage_without_creating_another(): void
    {
        $this->fakePortal();
        $booking = $this->book();

        $booking->update(['status' => 'confirmed']);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'crm.deal.update.json')
            && $r['id'] === 55 && $r['fields']['STAGE_ID'] === 'C3:WON');
        $this->assertSame(1, collect(Http::recorded())->filter(fn ($pair) => str_ends_with($pair[0]->url(), 'crm.deal.add.json'))->count());
    }

    public function test_nothing_is_sent_when_integration_is_disabled(): void
    {
        Setting::put('bitrix_enabled', false);
        Http::fake();

        $this->book();

        Http::assertNothingSent();
        $this->assertSame(0, IntegrationLog::query()->count());
    }

    public function test_portal_error_is_logged_and_booking_is_still_accepted(): void
    {
        Http::fake([
            self::HOOK.'crm.duplicate.findbycomm.json' => Http::response(['result' => []]),
            self::HOOK.'crm.contact.add.json' => Http::response(['result' => 11]),
            self::HOOK.'crm.deal.add.json' => Http::response(['error' => 'ERROR_CORE', 'error_description' => 'Поле UF_CRM_CAR не найдено'], 400),
        ]);

        $booking = $this->book();

        $this->assertNull($booking->crm_id);
        $log = IntegrationLog::query()->sole();
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('UF_CRM_CAR', $log->error);
    }

    public function test_portal_outage_never_breaks_customer_booking(): void
    {
        Http::fake(fn () => throw new ConnectionException('portal is down'));

        $booking = $this->book();

        $this->assertNull($booking->crm_id);
        $this->assertSame('failed', IntegrationLog::query()->sole()->status);
    }

    public function test_new_callback_lead_becomes_crm_lead(): void
    {
        $this->fakePortal();

        $lead = Lead::query()->create(['type' => 'callback', 'phone' => '+7 978 000-00-01', 'name' => 'Анна', 'status' => 'new']);

        $this->assertSame('77', $lead->fresh()->crm_id);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'crm.lead.add.json')
            && $r['fields']['NAME'] === 'Анна' && $r['fields']['PHONE'][0]['VALUE'] === '+79780000001');
    }

    public function test_webhook_url_is_stored_encrypted(): void
    {
        $raw = DB::table('settings')->where('key', 'bitrix_webhook_url')->value('value');

        $this->assertStringNotContainsString('secret-token', (string) $raw);
        $this->assertSame(self::HOOK, Setting::secret('bitrix_webhook_url'));
    }

    public function test_inbound_webhook_requires_token_and_updates_booking_status(): void
    {
        $this->fakePortal();
        $booking = $this->book();
        Setting::put('bitrix_inbound_enabled', true);
        Setting::putSecret('bitrix_inbound_token', 'app-token');

        $payload = ['event' => 'ONCRMDEALUPDATE', 'data' => ['FIELDS' => ['ID' => '55']]];

        $this->post('/integrations/bitrix24/webhook', [...$payload, 'auth' => ['application_token' => 'wrong']])->assertForbidden();
        $this->assertSame('new', $booking->fresh()->status);

        $this->post('/integrations/bitrix24/webhook', [...$payload, 'auth' => ['application_token' => 'app-token']])->assertOk();
        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    public function test_admin_pages_render_without_exposing_the_webhook(): void
    {
        $this->fakePortal();
        $this->book();
        config(['app.env' => 'local']);
        $this->actingAs(User::factory()->create());

        $this->get('/admin/integrations')->assertOk()->assertSee('Битрикс24')->assertSee('Сохранён')->assertDontSee('secret-token');
        $this->get('/admin/integration-logs')->assertOk()->assertSee('Бронь №1');
    }

    public function test_check_connection_loads_portal_directories_and_keeps_saved_secret(): void
    {
        Http::fake([
            self::HOOK.'profile.json' => Http::response(['result' => ['ID' => 1, 'NAME' => 'Иван', 'LAST_NAME' => 'Романов']]),
            self::HOOK.'crm.category.list.json' => Http::response(['result' => ['categories' => [['id' => 0, 'name' => 'Общая'], ['id' => 3, 'name' => 'Аренда']]]]),
            self::HOOK.'crm.status.list.json' => Http::sequence()
                ->push(['result' => [['STATUS_ID' => 'NEW', 'NAME' => 'Новая']]])
                ->push(['result' => [['STATUS_ID' => 'C3:NEW', 'NAME' => 'Заявка'], ['STATUS_ID' => 'C3:WON', 'NAME' => 'Выдана']]])
                ->push(['result' => [['STATUS_ID' => 'NEW', 'NAME' => 'Не обработан']]])
                ->push(['result' => [['STATUS_ID' => 'WEB', 'NAME' => 'Веб-сайт']]]),
            self::HOOK.'user.get.json' => Http::response(['result' => [['ID' => '9', 'NAME' => 'Мария', 'LAST_NAME' => 'Менеджер']]]),
            self::HOOK.'crm.deal.fields.json' => Http::response(['result' => ['TITLE' => ['title' => 'Название'], 'UF_CRM_CAR' => ['formLabel' => 'Машина']]]),
            self::HOOK.'crm.lead.fields.json' => Http::response(['result' => ['UF_CRM_DATES' => ['listLabel' => 'Даты']]]),
        ]);
        config(['app.env' => 'local']);
        $this->actingAs(User::factory()->create());

        Livewire::test(Integrations::class)->callAction('checkBitrix')->assertHasNoErrors();

        $dir = Setting::get('bitrix_directory');
        $this->assertSame('Иван Романов', $dir['portal_user']);
        $this->assertSame([0 => 'Общая', 3 => 'Аренда'], $dir['categories']);
        $this->assertSame(['C3:NEW' => 'Заявка', 'C3:WON' => 'Выдана'], $dir['deal_stages'][3]);
        $this->assertSame([9 => 'Мария Менеджер'], $dir['users']);
        $this->assertSame(['UF_CRM_CAR' => 'Машина (UF_CRM_CAR)'], $dir['deal_fields']);
        $this->assertSame(self::HOOK, Setting::secret('bitrix_webhook_url'));
    }
}
