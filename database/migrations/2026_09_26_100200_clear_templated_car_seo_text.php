<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * У импортированных машин seo_text был одинаковым шаблоном («Выбирайте … напрокат для поездок по Крыму…»).
 * Одинаковый текст на 147 страницах поисковики считают дублем. Теперь внизу карточки —
 * собранный SEO-блок со ссылками (App\Support\CarSeoLinks), а seo_text — только для ручного текста.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('cars')
            ->where('seo_text', 'like', 'Выбирайте % напрокат для поездок по Крыму. Оставьте заявку на car-on-time.ru%')
            ->update(['seo_text' => null]);
    }

    public function down(): void
    {
        //
    }
};
