<?php

namespace Tests\Feature;

use App\Models\Faq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaqTest extends TestCase
{
    use RefreshDatabase;

    public function test_faq_page_is_grouped_with_anchors_and_schema(): void
    {
        // Вопросы засеяны миграцией из database/data/faq.php
        $this->assertGreaterThanOrEqual(30, Faq::query()->count());

        $this->get('/faq')->assertOk()
            ->assertSee('Требования и документы')
            ->assertSee('id="q-vozrast-18-let"', false)
            ->assertSee('Можно ли взять машину в 18 лет?')
            ->assertSee('с 22 лет при водительском стаже от 2 лет')
            ->assertSee('"@type":"FAQPage"', false);
    }

    public function test_hidden_questions_are_not_shown_and_home_uses_featured(): void
    {
        Faq::query()->where('slug', 'kurenie')->update(['is_published' => false]);

        $this->get('/faq')->assertDontSee('Можно ли курить в машине?');
        $this->get('/')->assertSee('Можно ли взять машину в 18 лет?')->assertDontSee('Что делать при ДТП?');
    }

    public function test_answers_contain_no_unverified_placeholders(): void
    {
        foreach (Faq::query()->get() as $faq) {
            $this->assertStringNotContainsString('TODO', $faq->answer);
            $this->assertStringNotContainsString('{', $faq->answer, $faq->question);
        }
    }
}
