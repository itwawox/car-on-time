<?php

namespace App\Console\Commands;

use App\Models\BodyType;
use App\Models\Brand;
use App\Models\Car;
use App\Models\CarClass;
use App\Models\Extra;
use App\Models\Faq;
use App\Models\Feature;
use App\Models\Location;
use App\Models\Page;
use App\Models\Partner;
use App\Models\Season;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ImportLegacyCatalogCommand extends Command
{
    protected $signature = 'catalog:import-legacy {--fresh : Wipe catalog tables first}';

    protected $description = 'Import cars, prices and locations from _legacy HTML template';

    public function handle(): int
    {
        $legacy = base_path('_legacy');
        if (! is_dir($legacy)) {
            $this->error('_legacy folder not found');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            Car::query()->delete();
            Brand::query()->delete();
            Location::query()->delete();
        }

        $this->seedSettings();
        $this->seedTaxonomy();
        $this->seedSeasons();
        $this->seedExtras();
        $this->importLocations($legacy);
        $this->importCars($legacy);
        $this->seedPages($legacy);
        $this->seedFaqs();

        $this->info('Cars: '.Car::count());
        $this->info('Locations: '.Location::count());

        return self::SUCCESS;
    }

    private function seedSettings(): void
    {
        $defaults = [
            'brand_name' => 'Car on Time',
            'phone' => '+7 978 948 48 48',
            'phone_raw' => '+79789484848',
            'phone_alt' => '+7 978 948 48 48',
            'phone_alt_raw' => '+79789484848',
            'telegram' => 'https://t.me/+79789484848',
            'whatsapp' => 'https://wa.me/79789484848',
            'max' => 'https://max.ru/u/+79789484848',
            'email' => 'info@car-on-time.ru',
            'address' => '297536, Республика Крым, Симферопольский р-н, с. Укромное, ул. Молодёжная, 64-б',
            'hours' => 'Заявки принимаем круглосуточно',
            'min_age' => 22,
            'min_experience' => 2,
            'daily_km' => 300,
            'disclaimer' => 'Изображения автомобилей являются примером. Цвет и комплектация подтверждаются при заявке. Предложение не является публичной офертой.',
        ];

        foreach ($defaults as $key => $value) {
            Setting::put($key, $value);
        }
    }

    private function seedTaxonomy(): void
    {
        Partner::query()->firstOrCreate(['name' => 'Основной парк']);

        $classes = [
            ['name' => 'Эконом', 'slug' => 'ekonom', 'title' => 'Аренда авто эконом класса в Крыму'],
            ['name' => 'Средний', 'slug' => 'srednij', 'title' => 'Аренда авто среднего класса в Крыму'],
            ['name' => 'Бизнес', 'slug' => 'biznes', 'title' => 'Аренда авто бизнес класса в Крыму'],
        ];
        foreach ($classes as $i => $row) {
            CarClass::query()->updateOrCreate(['slug' => $row['slug']], $row + ['sort' => $i + 1]);
        }

        $bodies = [
            ['name' => 'Седан', 'slug' => 'sedan', 'title' => 'Аренда седана в Крыму'],
            ['name' => 'Кроссовер', 'slug' => 'krossover', 'title' => 'Аренда кроссовера в Крыму'],
            ['name' => 'Кабриолет', 'slug' => 'kabriolet', 'title' => 'Аренда кабриолета в Крыму'],
            ['name' => 'Минивэн', 'slug' => 'miniven', 'title' => 'Аренда минивэна в Крыму'],
            ['name' => 'Электро', 'slug' => 'elektro', 'title' => 'Аренда электромобиля в Крыму'],
        ];
        foreach ($bodies as $i => $row) {
            BodyType::query()->updateOrCreate(['slug' => $row['slug']], $row + ['sort' => $i + 1]);
        }

        foreach (['Кондиционер' => 'ac', 'USB' => 'usb', 'AUX' => 'aux', '4WD' => '4wd'] as $name => $slug) {
            Feature::query()->updateOrCreate(['slug' => $slug], ['name' => $name]);
        }
    }

    private function seedSeasons(): void
    {
        $rows = [
            ['name' => 'С 01.06 по 04.10', 'starts_month' => 6, 'starts_day' => 1, 'ends_month' => 10, 'ends_day' => 4, 'sort' => 1],
            ['name' => 'С 05.10 по 01.11', 'starts_month' => 10, 'starts_day' => 5, 'ends_month' => 11, 'ends_day' => 1, 'sort' => 2],
            ['name' => 'С 02.11 по 29.12', 'starts_month' => 11, 'starts_day' => 2, 'ends_month' => 12, 'ends_day' => 29, 'sort' => 3],
            ['name' => 'С 30.12 по 31.12', 'starts_month' => 12, 'starts_day' => 30, 'ends_month' => 12, 'ends_day' => 31, 'sort' => 4],
            ['name' => 'С 01.01 по 10.01', 'starts_month' => 1, 'starts_day' => 1, 'ends_month' => 1, 'ends_day' => 10, 'sort' => 5],
            ['name' => 'С 11.01 по 31.05', 'starts_month' => 1, 'starts_day' => 11, 'ends_month' => 5, 'ends_day' => 31, 'sort' => 6],
        ];

        foreach ($rows as $row) {
            Season::query()->updateOrCreate(['name' => $row['name']], $row);
        }
    }

    private function seedExtras(): void
    {
        foreach ([
            ['name' => 'Детское кресло', 'slug' => 'baby-seat', 'is_free' => true],
            ['name' => 'Бустер', 'slug' => 'baby-booster', 'is_free' => true],
            ['name' => 'Люлька', 'slug' => 'baby-lulka', 'is_free' => true],
            ['name' => 'Доп. водитель', 'slug' => 'add-driver', 'is_free' => true],
        ] as $i => $row) {
            Extra::query()->updateOrCreate(['slug' => $row['slug']], $row + ['price_per_day' => 0, 'sort' => $i + 1]);
        }
    }

    private function importLocations(string $legacy): void
    {
        $html = File::get($legacy.'/avto/prokat-avto-renault-logan-na-mehanike-v-krymu.html');
        preg_match_all(
            '/<option\s+([^>]+)>([^<]+)<\/option>/u',
            $html,
            $matches,
            PREG_SET_ORDER
        );

        $seen = [];
        $sort = 0;
        foreach ($matches as $match) {
            $attrs = $match[1];
            $label = html_entity_decode(trim($match[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (! preg_match('/name="from"/', substr($html, max(0, strpos($html, $match[0]) - 400), 400)) && $sort === 0) {
                // keep going, first select is from
            }
            if (! str_contains($attrs, 'data-dest-price')) {
                continue;
            }
            if (isset($seen[$label])) {
                continue;
            }
            $seen[$label] = true;

            $get = function (string $key) use ($attrs): ?string {
                return preg_match('/'.$key.'="([^"]*)"/', $attrs, $m) ? $m[1] : null;
            };

            $price3 = $this->intOrNull($get('data-dest-price'));
            $price1 = $this->intOrNull($get('data-dest-1-price'));
            $price2 = $this->intOrNull($get('data-dest-2-price'));
            $night = $this->intOrNull($get('data-dest-night-price'));

            Location::query()->updateOrCreate(
                ['name' => $label],
                [
                    'slug' => Str::slug($label, '-', 'ru'),
                    'short_name' => $get('data-sm-name') ?: $label,
                    'type' => $this->locationType($label),
                    'hours_from' => $get('data-dest_start_hour'),
                    'hours_to' => $get('data-dest_finish_hour'),
                    'price_1_day' => $price1,
                    'price_2_days' => $price2,
                    'price_3plus' => $price3,
                    'night_price' => $night,
                    'is_default_pickup' => str_contains($label, 'Запорожская'),
                    'seo_enabled' => in_array($this->locationType($label), ['city', 'airport'], true),
                    'sort' => $sort++,
                    'is_active' => true,
                ]
            );
        }
    }

    private function importCars(string $legacy): void
    {
        $index = File::get($legacy.'/index.html');
        $classMap = [
            'ekonom-v-krymu.html' => 'ekonom',
            'srednij-klass-v-krymu.html' => 'srednij',
            'biznes-klass-v-krymu.html' => 'biznes',
            'krossovery-v-krymu.html' => 'krossover',
            'kabriolet-v-krymu.html' => 'kabriolet',
            'electro-v-krymu.html' => 'elektro',
            'minivehn-mikroavtobus-v-krymu.html' => 'miniven',
        ];

        $classSlugsByHref = [];
        foreach ($classMap as $file => $slug) {
            $path = $legacy.'/'.$file;
            if (! File::exists($path)) {
                continue;
            }
            $html = File::get($path);
            preg_match_all('#/avto/([a-z0-9-]+)#', $html, $m);
            foreach (array_unique($m[1]) as $carSlug) {
                $classSlugsByHref[$carSlug][] = $slug;
            }
        }

        preg_match_all(
            '#<li[^>]*onclick="location\.assign\(\'/avto/([a-z0-9-]+)\'\)"[\s\S]*?</li>#u',
            $index,
            $blocks,
            PREG_SET_ORDER
        );

        $partner = Partner::query()->first();
        $season = Season::query()->where('name', 'С 01.06 по 04.10')->first();
        $sort = 0;
        $imported = [];

        foreach ($blocks as $block) {
            $slug = $block[1];
            if (isset($imported[$slug])) {
                continue;
            }
            $html = $block[0];

            preg_match('#image/brand/([^"]+)\.png#', $html, $brandMatch);
            $brandName = $brandMatch[1] ?? 'Other';
            $brand = Brand::query()->firstOrCreate(
                ['slug' => Str::slug($brandName)],
                ['name' => $brandName, 'logo_path' => 'legacy-image/brand/'.$brandName.'.png']
            );

            preg_match('#<a href="https://rentcar777.ru/avto/[^"]+">([^<]+)</a>#u', $html, $nameMatch);
            $name = html_entity_decode(trim($nameMatch[1] ?? $slug), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            preg_match('#src="(image/auto/[^"]+)"#', $html, $imgMatch);
            $image = $imgMatch[1] ?? null;

            $prices = [];
            preg_match_all('#qty_price">(\d+)#', $html, $priceMatch);
            if (isset($priceMatch[1][0])) {
                $prices = array_map('intval', $priceMatch[1]);
            }

            $gearbox = (str_contains($name, 'М/T') || str_contains($name, 'М/Т') || str_contains($html, 'MT.png')) ? 'mt' : 'at';
            $seats = 5;
            if (preg_match('#charact_value">\s*(\d+)\s*<#', $html, $seatMatch)) {
                $seats = (int) $seatMatch[1];
            }

            $fuel = str_contains($html, 'el_icon') || str_contains(mb_strtolower($name), 'electro') ? 'electric' : 'petrol';
            if (str_contains(mb_strtolower($name), 'дизель') || str_contains($name, 'ДИЗЕЛЬ')) {
                $fuel = 'diesel';
            }

            $bodySlug = 'sedan';
            $classes = $classSlugsByHref[$slug] ?? ['srednij'];
            if (in_array('krossover', $classes, true)) {
                $bodySlug = 'krossover';
            } elseif (in_array('kabriolet', $classes, true) || str_contains(mb_strtolower($name), 'кабриолет')) {
                $bodySlug = 'kabriolet';
            } elseif (in_array('miniven', $classes, true) || str_contains($name, 'мест')) {
                $bodySlug = 'miniven';
            } elseif (in_array('elektro', $classes, true)) {
                $bodySlug = 'elektro';
            }

            $body = BodyType::query()->where('slug', $bodySlug)->first();
            $deposit = $this->guessDeposit($classes);

            $car = Car::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'partner_id' => $partner?->id,
                    'brand_id' => $brand->id,
                    'body_type_id' => $body?->id,
                    'name' => $name,
                    'model' => $name,
                    'gearbox' => $gearbox,
                    'fuel' => $fuel,
                    'seats' => $seats ?: 5,
                    'drivetrain' => (str_contains($name, '4WD') || str_contains($name, '4MATIC') || str_contains($name, 'xDrive')) ? '4wd' : 'fwd',
                    'deposit' => $deposit,
                    'min_age' => 22,
                    'min_experience' => 2,
                    'min_days' => 2,
                    'daily_km' => 300,
                    'description' => "Аренда {$name} в Крыму от Car on Time. Наличие подтвердим за 15 минут после заявки.",
                    'seo_text' => "Выбирайте {$name} напрокат для поездок по Крыму. Оставьте заявку на car-on-time.ru — подберём авто и согласуем выдачу.",
                    'seo_title' => "Аренда {$name} в Крыму | Car on Time",
                    'seo_description' => "Прокат {$name} в Крыму. Без предоплаты, доставка по Симферополю. Заявки принимаем круглосуточно.",
                    'status' => 'published',
                    'legacy_image' => $image ? 'legacy-image/'.substr($image, 6) : null,
                    'sort' => $sort++,
                ]
            );

            $classIds = CarClass::query()->whereIn('slug', array_values(array_unique(array_filter(
                array_map(fn ($s) => in_array($s, ['ekonom', 'srednij', 'biznes'], true) ? $s : null, $classes)
            ))))->pluck('id');
            if ($classIds->isEmpty()) {
                $classIds = CarClass::query()->where('slug', 'srednij')->pluck('id');
            }
            $car->classes()->sync($classIds);

            $featureIds = [];
            if (str_contains($html, 'Кондиционер') || str_contains($html, 'A/C')) {
                $featureIds[] = Feature::query()->where('slug', 'ac')->value('id');
            }
            if (str_contains($name, '4WD')) {
                $featureIds[] = Feature::query()->where('slug', '4wd')->value('id');
            }
            $car->features()->sync(array_filter($featureIds));

            $car->prices()->delete();
            $price23 = $prices[0] ?? 0;
            $price429 = $prices[1] ?? $price23;
            $price30 = $prices[2] ?? $price429;

            foreach (Season::all() as $row) {
                $car->prices()->create(['season_id' => $row->id, 'days_from' => 2, 'days_to' => 3, 'price' => $price23]);
                $car->prices()->create(['season_id' => $row->id, 'days_from' => 4, 'days_to' => 29, 'price' => $price429]);
                $car->prices()->create(['season_id' => $row->id, 'days_from' => 30, 'days_to' => null, 'price' => $price30]);
            }

            $imported[$slug] = true;
        }

        $this->info('Imported cars: '.count($imported));
    }

    private function seedPages(string $legacy): void
    {
        $usloviya = File::exists($legacy.'/usloviya-krym.html')
            ? File::get($legacy.'/usloviya-krym.html')
            : '';
        preg_match('/<div class="item-page">([\s\S]*?)<div class="clr">/u', $usloviya, $m);
        $conditions = $m[1] ?? '<p>Условия аренды уточняются при заявке.</p>';
        // Шаблон взят с чужого сайта: сначала меняем его контакты и ссылки на наши, потом название
        $conditions = preg_replace([
            '/[\w.+-]*rentcar777[\w.+-]*@[\w.-]+/i',
            '#https?://(www\.)?rentcar777\.ru#i',
            '#https://wa\.me/\d+#',
            '/\+7\s?\(?978\)?[\s-]?\d{3}[\s-]?\d{2}[\s-]?\d{2}/u',
        ], ['info@car-on-time.ru', '', 'https://wa.me/79789484848', '+7 978 948 48 48'], $conditions);
        $conditions = preg_replace('/RENTCAR777/i', 'Car on Time', $conditions);

        Page::query()->updateOrCreate(['slug' => 'usloviya'], [
            'title' => 'Условия аренды',
            'h1' => 'Условия аренды авто в Крыму',
            'content' => $conditions,
            'seo_title' => 'Условия аренды авто в Крыму | Car on Time',
            'seo_description' => 'Возраст 22 года, стаж 2 года, без предоплаты, залог возвращаем. Доставка по Крыму.',
            'is_published' => true,
        ]);

        Page::query()->updateOrCreate(['slug' => 'kontakty'], [
            'title' => 'Контакты',
            'h1' => 'Контакты Car on Time в Крыму',
            // Телефон, адрес и реквизиты выводятся из настроек сайта (partials/contacts-card)
            'content' => '<p>Подберём автомобиль под ваши даты и подтвердим наличие за 15 минут. Выдаём машины на стойке в аэропорту Симферополь и привозим по адресу по всему Крыму.</p>',
            'seo_title' => 'Контакты Car on Time | Прокат авто в Крыму',
            'seo_description' => 'Телефон, WhatsApp, Telegram и адрес выдачи авто в Симферополе.',
            'is_published' => true,
        ]);
    }

    private function seedFaqs(): void
    {
        $rows = [
            ['Это точное авто или пример?', 'Фото и цвет — пример класса. Конкретную машину подтвердим в заявке.'],
            ['Когда подтвердите наличие?', 'Обычно в течение 15 минут. Мы агент: уточняем авто у собственника и сразу пишем вам.'],
            ['Нужна ли предоплата?', 'Нет. Оплата при получении автомобиля.'],
            ['Что с залогом?', 'Залог берём при выдаче и возвращаем, когда сдаёте авто без замечаний.'],
            ['Можно ли доставку в Ялту, аэропорт, Севастополь?', 'Да. Стоимость доставки считается в заявке по точке выдачи и возврата.'],
        ];
        foreach ($rows as $i => [$q, $a]) {
            Faq::query()->updateOrCreate(['question' => $q], ['answer' => $a, 'sort' => $i + 1]);
        }
    }

    private function guessDeposit(array $classes): int
    {
        if (in_array('biznes', $classes, true)) {
            return 15000;
        }
        if (in_array('kabriolet', $classes, true)) {
            return 5000;
        }

        return 5000;
    }

    private function locationType(string $name): string
    {
        $n = mb_strtolower($name);
        if (str_contains($n, 'аэропорт') || str_contains($n, 'sip')) {
            return 'airport';
        }
        if (str_contains($n, 'запорожская')) {
            return 'office';
        }

        return 'city';
    }

    private function intOrNull(?string $value): ?int
    {
        if ($value === null || $value === '' || $value === '-') {
            return null;
        }

        return (int) $value;
    }
}
