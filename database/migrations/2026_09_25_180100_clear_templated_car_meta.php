<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * У импортированных машин title/description были сгенерированы по одному шаблону без цены.
 * Очищаем только точные совпадения — тогда работает шаблон из «SEO → Настройки SEO»
 * (с ценой «от … ₽/сутки»). Тексты, написанные вручную, не трогаем.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('cars')->get(['id', 'name', 'seo_title', 'seo_description']) as $car) {
            $update = [];
            if ($car->seo_title === 'Аренда '.$car->name.' в Крыму | Car on Time') {
                $update['seo_title'] = null;
            }
            if ($car->seo_description === 'Прокат '.$car->name.' в Крыму. Без предоплаты, доставка по Симферополю, работаем 24/7.') {
                $update['seo_description'] = null;
            }
            if ($update) {
                DB::table('cars')->where('id', $car->id)->update($update);
            }
        }
    }

    public function down(): void
    {
        //
    }
};
