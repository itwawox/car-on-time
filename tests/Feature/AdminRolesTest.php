<?php

namespace Tests\Feature;

use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_enter_admin_in_production(): void
    {
        config(['app.env' => 'production']);

        $this->actingAs(User::factory()->role('manager')->create())->get('/admin/bookings')->assertOk();
    }

    public function test_deactivated_or_unknown_role_user_is_locked_out(): void
    {
        config(['app.env' => 'production']);

        $this->actingAs(User::factory()->create(['is_active' => false]))->get('/admin/bookings')->assertForbidden();
        $this->actingAs(User::factory()->role('guest')->create())->get('/admin/bookings')->assertForbidden();
    }

    public function test_manager_works_with_bookings_but_not_integrations_or_content(): void
    {
        $this->actingAs(User::factory()->role('manager')->create());

        $this->get('/admin/bookings')->assertOk();
        $this->get('/admin/cars')->assertOk();
        $this->get('/admin/integrations')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/articles')->assertForbidden();
    }

    public function test_editor_works_with_content_only(): void
    {
        $this->actingAs(User::factory()->role('editor')->create());

        $this->get('/admin/articles')->assertOk();
        $this->get('/admin/reviews')->assertOk();
        $this->get('/admin/bookings')->assertForbidden();
        $this->get('/admin/integrations')->assertForbidden();
    }

    public function test_owner_manages_staff(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/users')->assertOk()->assertSee('Сотрудники');
        $this->get('/admin/integrations')->assertOk();
    }

    public function test_status_badges_show_labels_not_raw_codes(): void
    {
        $lead = Lead::query()->create(['type' => 'corporate', 'phone' => '+79780000001', 'status' => 'in_work']);
        $this->actingAs(User::factory()->create());

        Livewire::test(ListLeads::class)
            ->assertTableColumnFormattedStateSet('type', 'Юрлицо', $lead)
            ->assertTableColumnFormattedStateSet('status', 'В работе', $lead);
    }
}
