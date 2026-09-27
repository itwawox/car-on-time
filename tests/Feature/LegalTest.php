<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalTest extends TestCase
{
    use RefreshDatabase;

    public function test_legal_pages_exist_with_operator_requisites_and_are_linked_in_footer(): void
    {
        foreach (['politika-konfidencialnosti' => '152-ФЗ', 'soglasie-pdn' => 'отозвать согласие', 'cookie' => 'Яндекс.Метрика', 'rekomendatelnye-tehnologii' => '10.2-2', 'polzovatelskoe-soglashenie' => 'не являются публичной офертой'] as $slug => $text) {
            $this->get('/'.$slug)->assertOk()->assertSee($text)->assertSee('9102225144');
        }
        $this->get('/')->assertOk()
            ->assertSee('/rekomendatelnye-tehnologii', false)
            ->assertSee('data-cookie-banner', false)
            ->assertSee('ОГРН');
    }

    public function test_forms_have_unchecked_consent_checkbox(): void
    {
        $this->get('/yurlicam')->assertOk()->assertSee('name="pd_consent"', false)->assertDontSee('name="pd_consent" value="1" required checked', false);
        $this->get('/otzyvy')->assertOk()->assertSee('name="pd_consent"', false);
    }
}
