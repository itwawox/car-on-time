<?php

namespace Tests\Feature;

use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use App\Models\Partner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerLeadTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_page_shows_form_and_is_linked(): void
    {
        $this->get('/sdat-avto')->assertOk()
            ->assertSee('Заявка владельца')
            ->assertSee('name="type" value="owner"', false)
            ->assertSee('name="pd_consent"', false);
        $this->get('/')->assertSee('/sdat-avto', false);
        $this->get('/sitemap.xml')->assertSee('/sdat-avto');
    }

    public function test_owner_lead_stores_details(): void
    {
        $this->post('/obratnyj-zvonok', [
            'type' => 'owner', 'phone' => '+7 (978) 111-22-33', 'name' => 'Иван',
            'details' => ['owner_kind' => 'self_employed', 'city' => ' Симферополь ', 'car' => '<b>Kia Rio</b>', 'year' => '2021', 'gearbox' => 'at', 'cars_count' => '1'],
            'message' => 'Свободна с июня', 'pd_consent' => '1',
        ])->assertRedirect()->assertSessionHas('lead_sent');

        $lead = Lead::query()->sole();
        $this->assertSame('owner', $lead->type);
        $this->assertNotNull($lead->consent_at);
        $this->assertSame('Симферополь', $lead->details['city']);
        $this->assertSame('Kia Rio', $lead->details['car']);
        $this->assertContains('Кто: Самозанятый', $lead->detailLines());
        $this->assertContains('Коробка: Автомат', $lead->detailLines());
    }

    public function test_owner_lead_validation(): void
    {
        $this->postJson('/obratnyj-zvonok', ['type' => 'owner', 'pd_consent' => '1'])->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->postJson('/obratnyj-zvonok', ['type' => 'owner', 'phone' => '+79781112233'])->assertUnprocessable()->assertJsonValidationErrors('pd_consent');
        $this->postJson('/obratnyj-zvonok', ['type' => 'owner', 'phone' => '+79781112233', 'pd_consent' => '1', 'details' => ['year' => '1950']])
            ->assertUnprocessable()->assertJsonValidationErrors('details.year');
        $this->assertSame(0, Lead::query()->count());
    }

    public function test_callback_lead_ignores_details(): void
    {
        $this->postJson('/obratnyj-zvonok', ['type' => 'callback', 'phone' => '+79781112233', 'pd_consent' => '1', 'details' => ['car' => 'x']])->assertOk();
        $this->assertNull(Lead::query()->sole()->details);
    }

    public function test_create_partner_from_owner_lead(): void
    {
        $lead = Lead::query()->create(['type' => 'owner', 'phone' => '+79781112233', 'status' => 'new',
            'details' => ['car' => 'Kia Rio', 'year' => 2021], 'message' => 'Свободна с июня']);

        $partner = LeadResource::createPartner($lead);

        $this->assertInstanceOf(Partner::class, $partner);
        $this->assertSame('Владелец Kia Rio', $partner->name);
        $this->assertFalse($partner->is_active);
        $this->assertStringContainsString('Год: 2021', $partner->notes);
        $this->assertSame('in_work', $lead->fresh()->status);
    }
}
