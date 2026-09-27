<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 301-редиректы со старого сайта car-on-time.ru (PHP-версия 2010–2021).
 * Карточки машин ведут на ту же модель в новом каталоге (с учётом коробки),
 * если модели нет — на страницу марки, удалённые машины — в каталог.
 * Раздел «с водителем» закрыт — его адреса ведут в каталог.
 * Дальше правила редактируются в Filament: «Сайт → Редиректы».
 */
return new class extends Migration
{
    private const MAP = [
        ['/index.php', '/'], // главная
        ['/rent-terms.php', '/usloviya'], // условия
        ['/contact.php', '/kontakty'], // контакты
        ['/payment.php', '/usloviya'], // оплата → условия
        ['/car-listing.php', '/katalog'], // каталог
        ['/car-listing.php?class=0', '/katalog'], // все авто
        ['/car-listing.php?class=1', '/katalog'], // комфорт
        ['/car-listing.php?class=2', '/klass/biznes'], // бизнес
        ['/car-listing.php?class=3', '/klass/biznes'], // представительский
        ['/car-listing-driver.php', '/katalog'], // с водителем → каталог
        ['/booking.php', '/katalog'], // карточка без id
        ['/booking-driver.php', '/katalog'], // карточка без id
        ['/files/dogovor_arend_avtomobilya_bez_voditelya_dlya_fizlits.docx', '/usloviya'], // договор физлиц
        ['/files/dogovor_arendy_avtomobilya_bez_voditelya_dlya_yurlits.docx', '/usloviya'], // договор юрлиц
        ['/files/dop_soglashenie.docx', '/usloviya'], // доп. соглашение
        ['/booking-driver.php?carid=10', '/avto/prokat-avto-volkswagen-polo-v-krymu'], // Volkswagen Polo 2019 → Volkswagen Polo (A/T)
        ['/booking-driver.php?carid=108', '/marka/mazda'], // Mazda 5 2.0 AT → марка Mazda
        ['/booking-driver.php?carid=11', '/avto/arenda-avto-renault-sandero-stepway-v-krymu'], // Renault Sandero Stepway 2019 → Renault Sandero Stepway(2018-2021)
        ['/booking-driver.php?carid=110', '/marka/toyota'], // Toyota Alphard 3.5 AT → марка Toyota
        ['/booking-driver.php?carid=12', '/avto/prokat-renault-kaptur-2021-2023-v-kymu'], // Renault Kaptur 2018 → Renault KAPTUR (2021-2023)
        ['/booking-driver.php?carid=120', '/marka/ford'], // Ford Galaxy → марка Ford
        ['/booking-driver.php?carid=13', '/marka/ford'], // Ford Kuga 2018 → марка Ford
        ['/booking-driver.php?carid=14', '/avto/arenda-ford-focus-2-pokolenie-na-mehanike-v-krymu'], // Ford Focus 2018 → Ford Focus II рестайлинг (М/Т)
        ['/booking-driver.php?carid=15', '/katalog'], // (удалена)
        ['/booking-driver.php?carid=16', '/avto/prokat-hundai-creta-v-kymu'], // Hyundai Creta 1.6 AT → Hyundai Creta(2017-2020) Автомат
        ['/booking-driver.php?carid=17', '/avto/prokat-skoda-rapid-krym'], // Skoda Rapid 1.6 AT → Skoda Rapid(2016-2017)
        ['/booking-driver.php?carid=18', '/katalog'], // (удалена)
        ['/booking-driver.php?carid=19', '/avto/arenda-skoda-octavia-v-krymu'], // Skoda Octavia 2018 → Skoda Octavia
        ['/booking-driver.php?carid=20', '/marka/nissan'], // Nissan X-Trail 2.0 AT → марка Nissan
        ['/booking-driver.php?carid=21', '/marka/chevrolet'], // Chevrolet Orlando 1.8 AT → марка Chevrolet
        ['/booking-driver.php?carid=22', '/marka/mercedes'], // Mercedes Sprinter VIP → марка Mercedes
        ['/booking-driver.php?carid=23', '/katalog'], // Infiniti QX56 → каталог
        ['/booking-driver.php?carid=24', '/marka/lexus'], // LEXUS LX570 → марка Lexus
        ['/booking-driver.php?carid=25', '/marka/audi'], // Audi Q7 → марка Audi
        ['/booking-driver.php?carid=26', '/marka/audi'], // Audi A8 W12 → марка Audi
        ['/booking-driver.php?carid=27', '/marka/mercedes'], // Mercedes S500 W221 → марка Mercedes
        ['/booking-driver.php?carid=28', '/avto/prokat-avto-v-krymu-hyundai-h1-2016'], // Hyundai H1 → Hyundai H1(2016-2019)(8 мест)
        ['/booking-driver.php?carid=29', '/marka/toyota'], // Toyota Hiace → марка Toyota
        ['/booking-driver.php?carid=30', '/marka/mercedes'], // Mercedes Viano → марка Mercedes
        ['/booking-driver.php?carid=42', '/marka/ford'], // Ford S-Max → марка Ford
        ['/booking-driver.php?carid=43', '/avto/arenda-toyota-land-cruiser-v-krymu'], // Toyota Land Cruiser Prado → Toyota Land Cruiser Prado (ДИЗЕЛЬ)
        ['/booking-driver.php?carid=44', '/avto/arenda-toyota-land-cruiser-200-exclusive-v-krymu'], // Toyota Land Cruiser 200 new → Toyota Land Cruiser 200 (рестайлинг STYLE)(7 мест)
        ['/booking-driver.php?carid=45', '/marka/nissan'], // Nissan Patrol → марка Nissan
        ['/booking-driver.php?carid=47', '/marka/mitsubishi'], // Mitsubishi Outlander → марка Mitsubishi
        ['/booking-driver.php?carid=48', '/avto/arenda-krossover-toyota-rav4-v-krymu'], // Toyota RAV4 → Toyota RAV4
        ['/booking-driver.php?carid=49', '/avto/arenda-avtomobilya-nissan-almera-v-krymu'], // Nissan Almera → Nissan Almera
        ['/booking-driver.php?carid=51', '/avto/prokat-toyota-camry-55-v-krymu'], // Toyota Camry new → Toyota Camry 55 (2016-2018)
        ['/booking-driver.php?carid=52', '/marka/nissan'], // Nissan Teana new → марка Nissan
        ['/booking-driver.php?carid=53', '/marka/ford'], // Ford Mondeo → марка Ford
        ['/booking-driver.php?carid=57', '/avto/arenda-avto-hyundai-solaris-v-krymu'], // Hyundai Solaris → Hyundai Solaris(A/T)
        ['/booking-driver.php?carid=58', '/avto/prokat-avto-volkswagen-polo-v-krymu'], // Volkswagen Polo 2018 → Volkswagen Polo (A/T)
        ['/booking-driver.php?carid=59', '/avto/arenda-volkswagen-jetta-v-krymu'], // Volkswagen Jetta → Volkswagen Jetta VI(2014-2018)
        ['/booking-driver.php?carid=9', '/avto/prokat-skoda-rapid-krym'], // Skoda Rapid 2019 → Skoda Rapid(2016-2017)
        ['/booking-driver.php?carid=93', '/avto/prokat-avto-krossover-volkswagen-tiguan-1-4-tsi-krym'], // Volkswagen Tiguan 1.4 AT → Volkswagen Tiguan (1.4 tsi)
        ['/booking-driver.php?carid=95', '/marka/chevrolet'], // Chevrolet Cruze 1.6 AT → марка Chevrolet
        ['/booking-driver.php?carid=96', '/avto/prokat-toyota-camry-55-v-krymu'], // Toyota Camry 2.5 AT → Toyota Camry 55 (2016-2018)
        ['/booking-driver.php?carid=99', '/marka/volkswagen'], // Volkswagen Caddy → марка Volkswagen
        ['/booking.php?carid=1', '/katalog'], // (удалена)
        ['/booking.php?carid=10', '/avto/prokat-avto-volkswagen-polo-v-krymu'], // Volkswagen Polo 2019 → Volkswagen Polo (A/T)
        ['/booking.php?carid=108', '/marka/mazda'], // Mazda 5 2.0 AT → марка Mazda
        ['/booking.php?carid=11', '/avto/arenda-avto-renault-sandero-stepway-v-krymu'], // Renault Sandero Stepway 2019 → Renault Sandero Stepway(2018-2021)
        ['/booking.php?carid=12', '/avto/prokat-renault-kaptur-2021-2023-v-kymu'], // Renault Kaptur 2018 → Renault KAPTUR (2021-2023)
        ['/booking.php?carid=120', '/marka/ford'], // Ford Galaxy → марка Ford
        ['/booking.php?carid=13', '/marka/ford'], // Ford Kuga 2018 → марка Ford
        ['/booking.php?carid=14', '/avto/arenda-ford-focus-2-pokolenie-na-mehanike-v-krymu'], // Ford Focus 2018 → Ford Focus II рестайлинг (М/Т)
        ['/booking.php?carid=15', '/katalog'], // (удалена)
        ['/booking.php?carid=16', '/avto/prokat-hundai-creta-v-kymu'], // Hyundai Creta 1.6 AT → Hyundai Creta(2017-2020) Автомат
        ['/booking.php?carid=17', '/avto/prokat-skoda-rapid-krym'], // Skoda Rapid 1.6 AT → Skoda Rapid(2016-2017)
        ['/booking.php?carid=18', '/katalog'], // (удалена)
        ['/booking.php?carid=19', '/avto/arenda-skoda-octavia-v-krymu'], // Skoda Octavia 2018 → Skoda Octavia
        ['/booking.php?carid=2', '/avto/arenda-avto-hyundai-solaris-v-krymu'], // Hyundai Solaris 2021 → Hyundai Solaris(A/T)
        ['/booking.php?carid=20', '/marka/nissan'], // Nissan X-Trail 2.0 AT → марка Nissan
        ['/booking.php?carid=21', '/marka/chevrolet'], // Chevrolet Orlando 1.8 AT → марка Chevrolet
        ['/booking.php?carid=25', '/marka/audi'], // Audi Q7 → марка Audi
        ['/booking.php?carid=27', '/marka/mercedes'], // Mercedes S500 W221 → марка Mercedes
        ['/booking.php?carid=28', '/avto/prokat-avto-v-krymu-hyundai-h1-2016'], // Hyundai H1 → Hyundai H1(2016-2019)(8 мест)
        ['/booking.php?carid=30', '/marka/mercedes'], // Mercedes Viano → марка Mercedes
        ['/booking.php?carid=31', '/katalog'], // (удалена)
        ['/booking.php?carid=32', '/katalog'], // (удалена)
        ['/booking.php?carid=34', '/katalog'], // (удалена)
        ['/booking.php?carid=37', '/katalog'], // (удалена)
        ['/booking.php?carid=42', '/marka/ford'], // Ford S-Max → марка Ford
        ['/booking.php?carid=46', '/avto/arenda-avto-krossover-nissan-juke-krymu'], // Nissan Juke → Nissan Juke
        ['/booking.php?carid=47', '/marka/mitsubishi'], // Mitsubishi Outlander → марка Mitsubishi
        ['/booking.php?carid=48', '/avto/arenda-krossover-toyota-rav4-v-krymu'], // Toyota RAV4 → Toyota RAV4
        ['/booking.php?carid=49', '/avto/arenda-avtomobilya-nissan-almera-v-krymu'], // Nissan Almera → Nissan Almera
        ['/booking.php?carid=51', '/avto/prokat-toyota-camry-55-v-krymu'], // Toyota Camry new → Toyota Camry 55 (2016-2018)
        ['/booking.php?carid=52', '/marka/nissan'], // Nissan Teana new → марка Nissan
        ['/booking.php?carid=53', '/marka/ford'], // Ford Mondeo → марка Ford
        ['/booking.php?carid=55', '/avto/arenda-v-krymu-avto-premium-klassa-mercedes-e200-dt'], // Mercedes E350 → Mercedes E200 (Дизель)
        ['/booking.php?carid=56', '/marka/mercedes'], // Mercedes C180 → марка Mercedes
        ['/booking.php?carid=57', '/avto/arenda-avto-hyundai-solaris-v-krymu'], // Hyundai Solaris → Hyundai Solaris(A/T)
        ['/booking.php?carid=58', '/avto/prokat-avto-volkswagen-polo-v-krymu'], // Volkswagen Polo 2018 → Volkswagen Polo (A/T)
        ['/booking.php?carid=59', '/avto/arenda-volkswagen-jetta-v-krymu'], // Volkswagen Jetta → Volkswagen Jetta VI(2014-2018)
        ['/booking.php?carid=9', '/avto/prokat-skoda-rapid-krym'], // Skoda Rapid 2019 → Skoda Rapid(2016-2017)
        ['/booking.php?carid=93', '/avto/prokat-avto-krossover-volkswagen-tiguan-1-4-tsi-krym'], // Volkswagen Tiguan 1.4 AT → Volkswagen Tiguan (1.4 tsi)
        ['/booking.php?carid=95', '/marka/chevrolet'], // Chevrolet Cruze 1.6 AT → марка Chevrolet
        ['/booking.php?carid=96', '/avto/prokat-toyota-camry-55-v-krymu'], // Toyota Camry 2.5 AT → Toyota Camry 55 (2016-2018)
        ['/booking.php?carid=99', '/marka/volkswagen'], // Volkswagen Caddy → марка Volkswagen
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::MAP as [$from, $to]) {
            DB::table('redirects')->insertOrIgnore([
                'from_path' => $from,
                'to_path' => $to,
                'status' => 301,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Cache::forget('redirects:map');
    }

    public function down(): void
    {
        DB::table('redirects')->whereIn('from_path', array_column(self::MAP, 0))->delete();
        Cache::forget('redirects:map');
    }
};
