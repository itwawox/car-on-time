<?php

namespace Tests\Unit;

use App\Support\CmsHtml;
use PHPUnit\Framework\TestCase;

class CmsHtmlTest extends TestCase
{
    public function test_h1_becomes_h2_and_stray_closing_divs_are_removed(): void
    {
        $this->assertSame(
            '<h2 class="x">Заголовок</h2><p>Текст</p>',
            CmsHtml::clean('<h1 class="x">Заголовок</h1><p>Текст</p></div>'),
        );
        $this->assertSame('<div><p>ok</p></div>', CmsHtml::clean('<div><p>ok</p></div>'));
    }
}
