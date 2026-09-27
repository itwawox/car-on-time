<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Redirect;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OldSiteMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_site_urls_redirect_permanently(): void
    {
        $this->get('/contact.php')->assertRedirect('/kontakty')->assertStatus(301);
        $this->get('/rent-terms.php')->assertRedirect('/usloviya')->assertStatus(301);
        $this->get('/car-listing.php?class=2')->assertRedirect('/klass/biznes');
        // Неизвестный id и рекламные метки не ломают редирект
        $this->get('/booking.php?carid=99999')->assertRedirect('/katalog');
        $this->get('/contact.php?utm_source=yandex&yclid=1')->assertRedirect('/kontakty');
    }

    public function test_redirect_rules_from_admin_are_normalized_and_applied(): void
    {
        Redirect::query()->create(['from_path' => 'https://car-on-time.ru/old-page.php?b=2&a=1', 'to_path' => '/faq', 'status' => 301]);

        $this->assertDatabaseHas('redirects', ['from_path' => '/old-page.php?a=1&b=2']);
        $this->get('/old-page.php?a=1&b=2')->assertRedirect('/faq');
        $this->post('/old-page.php?a=1&b=2')->assertNotFound();
    }

    public function test_company_contacts_are_shown_with_single_phone(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('ООО «КАНСАЙ-ГРУПП»', $html);
        $this->assertStringContainsString('ИНН 9102225144', $html);
        $this->assertStringContainsString('+7 978 948 48 48', $html);
        $this->assertStringContainsString('"taxID":"9102225144"', $html);
        $this->assertStringContainsString('vk.com/avtoprokatkrym', $html);
        $this->assertStringNotContainsString('955 60 60', $html);
        $this->assertStringNotContainsString('913 44 13', $html);
        $this->assertStringNotContainsString('Железнодорожный', $html);
    }

    public function test_contacts_page_is_built_from_settings(): void
    {
        Page::query()->create(['slug' => 'kontakty', 'title' => 'Контакты', 'content' => '<p>Вступление</p>', 'is_published' => true]);

        $this->get('/kontakty')
            ->assertOk()
            ->assertSee('Стойка аренды в терминале аэропорта Симферополь')
            ->assertSee('с. Укромное')
            ->assertSee('с 2010 года');
    }

    public function test_metrika_is_only_rendered_in_production(): void
    {
        $this->assertSame('43419564', (string) Setting::get('yandex_metrika_id'));
        $this->get('/')->assertDontSee('mc.yandex.ru/metrika', false);
    }
}
