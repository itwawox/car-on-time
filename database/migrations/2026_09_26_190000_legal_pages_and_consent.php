<?php

use App\Models\Page;
use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Юридический блок: политика ПДн, согласие, cookie, рекомендательные технологии, пользовательское соглашение
 * (тексты — database/data/legal_pages.php, проект для юриста) + фиксация момента согласия в заявках.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['bookings', 'leads', 'reviews'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->timestamp('consent_at')->nullable();
            });
        }

        if (! Setting::get('ogrn')) {
            Setting::put('ogrn', '1179102004047'); // из договора аренды компании
        }

        $vars = [
            '{company}' => Setting::get('legal_name') ?: 'ООО «КАНСАЙ-ГРУПП»',
            '{inn}' => Setting::get('inn') ?: '9102225144',
            '{ogrn}' => Setting::get('ogrn'),
            '{address}' => Setting::get('address') ?: '297536, Республика Крым, Симферопольский р-н, с. Укромное, ул. Молодёжная, 64-б',
            '{email}' => Setting::get('email') ?: 'info@car-on-time.ru',
            '{phone}' => Setting::get('phone') ?: '+7 978 948 48 48',
            '{site}' => 'car-on-time.ru',
            '{date}' => now()->translatedFormat('j F Y'),
        ];

        foreach (require database_path('data/legal_pages.php') as $slug => [$title, $h1, $html]) {
            Page::query()->updateOrCreate(['slug' => $slug], [
                'title' => $title,
                'h1' => $h1,
                'content' => strtr($html, $vars),
                'seo_title' => $h1.' | Car on Time',
                'seo_description' => $h1.' — '.$vars['{company}'].', сайт car-on-time.ru.',
                'is_published' => true,
            ]);
        }
        Cache::forget('sitemap');
    }

    public function down(): void
    {
        foreach (['bookings', 'leads', 'reviews'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('consent_at'));
        }
    }
};
