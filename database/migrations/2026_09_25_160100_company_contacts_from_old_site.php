<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Контакты и реквизиты компании со старого сайта car-on-time.ru.
 * Телефон — единственный: +7 978 948 48 48.
 * Со страницы «Условия» убираются чужие контакты, попавшие туда из HTML-шаблона.
 */
return new class extends Migration
{
    private const SETTINGS = [
        'legal_name' => 'ООО «КАНСАЙ-ГРУПП»',
        'inn' => '9102225144',
        'address' => '297536, Республика Крым, Симферопольский р-н, с. Укромное, ул. Молодёжная, 64-б',
        'postal_code' => '297536',
        'address_region' => 'Республика Крым',
        'address_locality' => 'с. Укромное',
        'street_address' => 'ул. Молодёжная, 64-б',
        'pickup_point' => 'Стойка аренды в терминале аэропорта Симферополь',
        'founding_year' => '2010',
        'footer_about' => 'Аренда авто в Крыму с :year года. Подбираем машину под ваши даты и подтверждаем наличие за 15 минут.',
        'vk' => 'https://vk.com/avtoprokatkrym',
        'yandex_metrika_id' => '43419564',
        'email' => 'info@car-on-time.ru',
        'phone' => '+7 978 948 48 48',
        'phone_raw' => '+79789484848',
        'phone_alt' => '+7 978 948 48 48',
        'phone_alt_raw' => '+79789484848',
        'whatsapp' => 'https://wa.me/79789484848',
    ];

    public function up(): void
    {
        foreach (self::SETTINGS as $key => $value) {
            Setting::put($key, $value);
        }

        // Страница контактов: блок с данными строится из настроек, здесь — только вступление
        DB::table('pages')->where('slug', 'kontakty')->update([
            'h1' => 'Контакты Car on Time в Крыму',
            'content' => '<p>Подберём автомобиль под ваши даты и подтвердим наличие за 15 минут. Выдаём машины на стойке в аэропорту Симферополь и привозим по адресу по всему Крыму.</p>',
            'seo_description' => 'Car on Time — аренда авто в Крыму с 2010 года. Телефон +7 978 948 48 48, стойка в аэропорту Симферополь, доставка по Крыму. ООО «КАНСАЙ-ГРУПП».',
            'updated_at' => now(),
        ]);

        $page = DB::table('pages')->where('slug', 'usloviya')->first(['id', 'content']);
        if ($page) {
            $content = strtr((string) $page->content, [
                'https://wa.me/79781183755' => 'https://wa.me/79789484848',
                '+7(978) 118-37-55' => '+7 978 948 48 48',
                'mailto:Car on Timesimf@mail.ru' => 'mailto:info@car-on-time.ru',
                'Car on Timesimf@mail.ru' => 'info@car-on-time.ru',
            ]);
            // Битая ссылка из шаблона на несуществующую страницу
            $content = preg_replace('#<li>\s*<a href="https://Car on Time\.ru/[^"]*">[^<]*</a>\s*</li>#u', '<li><a href="/katalog">Требования к возрасту и стажу по конкретной модели указаны в карточке автомобиля</a></li>', $content);
            DB::table('pages')->where('id', $page->id)->update(['content' => $content, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Данные контента не откатываются
    }
};
