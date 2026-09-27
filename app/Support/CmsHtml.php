<?php

namespace App\Support;

/**
 * Страховка для HTML из админки (страницы, статьи, SEO-тексты).
 * H1 на странице должен быть один — заголовки первого уровня в тексте становятся H2.
 * Лишние закрывающие </div> (частое следствие копипаста) убираются, чтобы не ломать сетку страницы.
 */
class CmsHtml
{
    public static function clean(?string $html): string
    {
        $html = (string) $html;
        $html = (string) preg_replace(['/<h1(\s[^>]*)?>/i', '/<\/h1>/i'], ['<h2$1>', '</h2>'], $html);

        $extra = preg_match_all('/<\/div>/i', $html) - preg_match_all('/<div\b/i', $html);
        for ($i = 0; $i < $extra; $i++) {
            $pos = strripos($html, '</div>');
            $html = substr($html, 0, $pos).substr($html, $pos + 6);
        }

        return $html;
    }
}
