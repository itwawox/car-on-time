<?php

use App\Support\CmsHtml;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * В «Условиях» из чужого шаблона остались второй H1 (дубль заголовка страницы) и лишний </div>,
 * который ломал сетку страницы. Дублирующий H1 удаляется, прочие H1 становятся H2.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('pages')->get(['id', 'title', 'h1', 'content']) as $page) {
            $content = (string) $page->content;
            // Первый H1 с тем же текстом, что заголовок страницы, — просто дубль
            $content = (string) preg_replace('/^\s*<h1[^>]*>\s*'.preg_quote(trim(strip_tags((string) ($page->h1 ?: $page->title))), '/').'\s*<\/h1>\s*/iu', '', $content);
            $clean = CmsHtml::clean($content);
            if ($clean !== $page->content) {
                DB::table('pages')->where('id', $page->id)->update(['content' => $clean, 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        //
    }
};
