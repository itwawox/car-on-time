<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SEO-поля марок и посадочные страницы городов в БД (раньше были в коде App\Support\CrimeaCities).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->string('h1')->nullable()->after('slug');
            $table->string('seo_title')->nullable()->after('h1');
            $table->text('seo_description')->nullable()->after('seo_title');
            $table->text('intro')->nullable()->after('seo_description');
            $table->longText('seo_text')->nullable()->after('intro');
        });

        foreach (['car_classes', 'body_types'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->longText('seo_text')->nullable()->after('description');
            });
        }

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('h1')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->text('intro')->nullable();
            $table->longText('seo_text')->nullable();
            $table->string('card_subtitle')->nullable();
            $table->boolean('show_on_home')->default(false);
            $table->boolean('show_in_footer')->default(false);
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        $now = now();
        foreach ($this->cities() as $city) {
            DB::table('cities')->insert($city + ['is_published' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');

        foreach (['car_classes', 'body_types'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('seo_text'));
        }

        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn(['h1', 'seo_title', 'seo_description', 'intro', 'seo_text']);
        });
    }

    private function cities(): array
    {
        return [
            [
                'slug' => 'simferopol',
                'name' => 'Симферополь',
                'h1' => 'Аренда авто в Симферополе',
                'seo_title' => 'Аренда авто в Симферополе | Car on Time',
                'seo_description' => 'Прокат автомобилей в Симферополе: выдача в городе и у офиса. Без предоплаты, подтверждение за 15 минут.',
                'intro' => 'Выдаём машины на стойке в аэропорту Симферополь, привозим по адресу в городе или на вокзал. При аренде от 3 суток доставка по городу днём обычно бесплатна. Наличие уточняем и перезваниваем за 15 минут.',
                'card_subtitle' => 'Офис и по адресу',
                'show_on_home' => true,
                'show_in_footer' => false,
                'sort' => 1,
            ],
            [
                'slug' => 'simferopol-aeroport',
                'name' => 'Аэропорт Симферополя',
                'h1' => 'Аренда авто в аэропорту Симферополя',
                'seo_title' => 'Прокат авто аэропорт Симферополь SIP | Car on Time',
                'seo_description' => 'Встреча с табличкой в аэропорту Симферополя. Посчитаем доставку в заявке, авто подтвердим за 15 минут.',
                'intro' => 'Удобный сценарий после рейса: оставляете заявку с номером рейса, мы подтверждаем машину и выдаём её на стойке аренды в терминале аэропорта Симферополь. Ночная выдача считается отдельно.',
                'card_subtitle' => 'Встреча к рейсу',
                'show_on_home' => true,
                'show_in_footer' => true,
                'sort' => 2,
            ],
            [
                'slug' => 'yalta',
                'name' => 'Ялта',
                'h1' => 'Аренда авто в Ялте',
                'seo_title' => 'Аренда авто в Ялте | Car on Time',
                'seo_description' => 'Прокат авто с доставкой в Ялту. Кроссоверы для серпантина и кабриолеты к морю. Без предоплаты.',
                'intro' => 'Доставляем авто в Ялту на автовокзал или по адресу. Для ЮБК чаще берут автомат и клиренс повыше. Стоимость доставки видна в калькуляторе на карточке.',
                'card_subtitle' => 'ЮБК',
                'show_on_home' => true,
                'show_in_footer' => true,
                'sort' => 3,
            ],
            [
                'slug' => 'alushta',
                'name' => 'Алушта',
                'h1' => 'Аренда авто в Алуште',
                'seo_title' => 'Аренда авто в Алуште | Car on Time',
                'seo_description' => 'Прокат автомобилей в Алуште с доставкой от Car on Time. Подберём класс под семью или пару.',
                'intro' => 'Алушта — частая точка выдачи по пути из Симферополя на ЮБК. Привезём к автовокзалу или на адрес отеля в дневное окно доставки.',
                'card_subtitle' => '',
                'show_on_home' => false,
                'show_in_footer' => false,
                'sort' => 4,
            ],
            [
                'slug' => 'sevastopol',
                'name' => 'Севастополь',
                'h1' => 'Аренда авто в Севастополе',
                'seo_title' => 'Аренда авто в Севастополе | Car on Time',
                'seo_description' => 'Прокат авто с доставкой в Севастополь: центр, Инкерман, Фиолент, Учкуевка.',
                'intro' => 'По Севастополю несколько точек: автовокзал, адрес в городе, Фиолент, Учкуевка. Выберите нужную в заявке — цена доставки подставится сама.',
                'card_subtitle' => 'Доставка',
                'show_on_home' => true,
                'show_in_footer' => true,
                'sort' => 5,
            ],
            [
                'slug' => 'evpatoria',
                'name' => 'Евпатория',
                'h1' => 'Аренда авто в Евпатории',
                'seo_title' => 'Аренда авто в Евпатории | Car on Time',
                'seo_description' => 'Прокат авто в Евпатории: семейные седаны и кроссоверы, детское кресло бесплатно.',
                'intro' => 'Евпатория — западное побережье, удобны автоматы и 5-местные седаны. Доставка на вокзал или по адресу, кресло и бустер не тарифицируем.',
                'card_subtitle' => 'Запад',
                'show_on_home' => true,
                'show_in_footer' => false,
                'sort' => 6,
            ],
            [
                'slug' => 'feodosiya',
                'name' => 'Феодосия',
                'h1' => 'Аренда авто в Феодосии',
                'seo_title' => 'Аренда авто в Феодосии | Car on Time',
                'seo_description' => 'Прокат автомобилей в Феодосии с доставкой. Коктебель и Орджоникидзе — соседние точки.',
                'intro' => 'На востоке Крыма чаще берут кроссовер: трасса плюс поездки к бухтам. Доставка в Феодосию, Коктебель и Приморский считается в заявке.',
                'card_subtitle' => 'Восток',
                'show_on_home' => true,
                'show_in_footer' => false,
                'sort' => 7,
            ],
            [
                'slug' => 'saki',
                'name' => 'Саки',
                'h1' => 'Аренда авто в Саках',
                'seo_title' => 'Аренда авто в Саках | Car on Time',
                'seo_description' => 'Прокат авто в Саках и Новофёдоровке. Короткая доставка из Симферополя.',
                'intro' => 'Саки близко к Симферополю, доставка дешевле, чем на ЮБК. Подходит, если живёте у моря и ездите в город за продуктами и на экскурсии.',
                'card_subtitle' => '',
                'show_on_home' => false,
                'show_in_footer' => false,
                'sort' => 8,
            ],
        ];
    }
};
