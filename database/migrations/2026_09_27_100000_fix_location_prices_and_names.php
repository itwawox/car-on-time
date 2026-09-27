<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Данные точек выдачи: «1 ₽» за доставку на 1–2 суток — артефакт импорта (калькулятор прибавлял 1 ₽).
 * Аэропорт — 800 ₽ (решение владельца), у остальных «1» убираем: действует цена «от 3 суток».
 * Пробелы в названиях: «ул.Запорожская 12» → «ул. Запорожская 12», «Симферополь,ул.» → «Симферополь, ул.».
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('locations')->whereIn('id', [4, 5])->update(['price_1_day' => 800, 'price_2_days' => 800]);
        DB::table('locations')->where('price_1_day', 1)->update(['price_1_day' => null]);
        DB::table('locations')->where('price_2_days', 1)->update(['price_2_days' => null]);

        foreach (DB::table('locations')->get(['id', 'name', 'short_name']) as $l) {
            $fix = fn (?string $s) => $s === null ? null : preg_replace(['/,(?=\S)/u', '/\bул\.(?=\S)/u'], [', ', 'ул. '], $s);
            DB::table('locations')->where('id', $l->id)->update(['name' => $fix($l->name), 'short_name' => $fix($l->short_name)]);
        }
    }

    public function down(): void {}
};
