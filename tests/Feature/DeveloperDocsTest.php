<?php

namespace Tests\Feature;

use App\Filament\Pages\DeveloperDocs;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeveloperDocsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_reads_every_section(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/docs')->assertOk()->assertSee('Документация для разработчиков')->assertSee('Как устроен сайт: где что хранится');

        foreach (DeveloperDocs::sections() as $slug => $section) {
            $this->assertNotSame('', $section['title']);
            $this->get('/admin/docs?section='.$slug)->assertOk()->assertSee('<h1>'.e($section['title']).'</h1>', false);
        }
    }

    public function test_deploy_answers_are_in_docs(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/docs?section=obnovlenie-boevogo-saita')->assertOk()->assertSee('Run workflow')->assertSee('bash deploy/release.sh');
        $this->get('/admin/docs?section=pervaya-nastroika-servera')->assertOk()->assertSee('/opt/php/8.4/bin/php')->assertSee('Планировщик CRON');
        $this->get('/admin/docs?section=izmeneniya-bazy')->assertOk()->assertSee('migrate:fresh');
    }

    public function test_docs_are_for_owner_only(): void
    {
        $this->actingAs(User::factory()->role('manager')->create());

        $this->get('/admin/docs')->assertForbidden();
    }
}
