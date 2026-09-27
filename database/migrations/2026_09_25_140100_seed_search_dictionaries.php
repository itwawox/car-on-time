<?php

use App\Support\Search\SearchSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Переносит словари умного поиска из кода в БД, чтобы дальше их вели в админке.
 * Существующие значения не затираются — только дополняются.
 */
return new class extends Migration
{
    private const BRAND_ALIASES = [
        'renault' => ['рено', 'рэно', 'reno'],
        'lada' => ['лада', 'ваз', 'vaz', 'жигули'],
        'nissan' => ['ниссан', 'нисан'],
        'hyundai' => ['хендай', 'хендэ', 'хундай', 'хюндай', 'хёндай', 'хёндэ', 'хендаи', 'hundai', 'hyndai'],
        'volkswagen' => ['фольксваген', 'фольц', 'вольксваген', 'vw', 'фв', 'volkswagon'],
        'ford' => ['форд'],
        'kia' => ['киа', 'кия'],
        'skoda' => ['шкода'],
        'jetta' => ['джетта'],
        'kaiyi' => ['кайи', 'кайый', 'каи'],
        'omoda' => ['омода'],
        'toyota' => ['тойота', 'таета', 'тоета'],
        'changan' => ['чанган', 'чанъань', 'чангань'],
        'haval' => ['хавал', 'хавейл', 'хаваль'],
        'geely' => ['джили', 'жили', 'гили'],
        'chery' => ['чери', 'черри', 'cherry'],
        'jaecoo' => ['джейку', 'джаеку', 'джеку', 'jaeku'],
        'mazda' => ['мазда'],
        'jetour' => ['джетур', 'жетур'],
        'bmw' => ['бмв', 'бэмвэ', 'бумер', 'бэха'],
        'mercedes' => ['мерседес', 'мерс', 'мерин', 'benz', 'бенц', 'mercedesbenz'],
        'exeed' => ['эксид', 'иксид', 'exid'],
        'mini' => ['мини', 'cooper', 'купер'],
        'genesis' => ['генезис', 'дженезис'],
        'audi' => ['ауди'],
        'lexus' => ['лексус'],
        'tank' => ['танк'],
        'mitsubishi' => ['митсубиси', 'мицубиси', 'митсубиши', 'мицубиши', 'mitsubisi'],
        'voyah' => ['воя', 'воях', 'вояж'],
        'li' => ['ли', 'лисян', 'lixiang', 'li auto'],
        'chevrolet' => ['шевроле', 'шеви', 'шевролет', 'chevy'],
        'porsche' => ['порше', 'порш'],
    ];

    private const CATEGORY_ALIASES = [
        'car_classes' => [
            'ekonom' => ['эконом', 'economy', 'бюджет', 'дешевые'],
            'srednij' => ['средний', 'комфорт', 'comfort', 'middle'],
            'biznes' => ['бизнес', 'business', 'премиум', 'premium', 'люкс'],
        ],
        'body_types' => [
            'krossover' => ['кроссовер', 'кросовер', 'crossover', 'suv', 'паркетник', 'внедорожник'],
            'miniven' => ['минивэн', 'минивен', 'minivan', 'микроавтобус', 'бус', 'van'],
            'kabriolet' => ['кабриолет', 'кабрик', 'cabrio', 'convertible', 'без крыши'],
            'elektro' => ['электро', 'электромобиль', 'ev'],
            'sedan' => ['седан', 'sedan'],
        ],
    ];

    private const TERM_ALIASES = [
        'solaris' => ['солярис', 'соларис'],
        'creta' => ['крета'],
        'jolion' => ['джолион', 'жолион'],
        'eado' => ['эадо', 'идо'],
        'qashqai' => ['кашкай'],
        'juke' => ['жук', 'джук'],
        'tiguan' => ['тигуан'],
        'kaptur' => ['каптюр', 'каптур'],
        'largus' => ['ларгус'],
        'stepway' => ['степвей'],
        'sandero' => ['сандеро'],
        'xline' => ['икслайн', 'хлайн'],
        'rav4' => ['рав4', 'рав', 'rav'],
        'ix35' => ['ix', 'икс35'],
        'granta' => ['гранта'],
        'vesta' => ['веста'],
        '4x4' => ['нива', 'niva'],
        'cabrio' => ['кабрио', 'кабриолет'],
        'рестайлинг' => ['restyling', 'рестаил'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::BRAND_ALIASES as $slug => $aliases) {
            $this->appendAliases('brands', $slug, $aliases);
        }

        foreach (self::CATEGORY_ALIASES as $table => $rows) {
            foreach ($rows as $slug => $aliases) {
                $this->appendAliases($table, $slug, $aliases);
            }
        }

        foreach (self::TERM_ALIASES as $term => $synonyms) {
            DB::table('search_synonyms')->insertOrIgnore([
                'term' => $term,
                'synonyms' => json_encode($synonyms, JSON_UNESCAPED_UNICODE),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (SearchSettings::DEFAULTS as $key => $value) {
            DB::table('settings')->insertOrIgnore([
                'group' => SearchSettings::GROUP,
                'key' => $key,
                'value' => json_encode($value, JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        cache()->forget('site_settings');
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', array_keys(SearchSettings::DEFAULTS))->delete();
        DB::table('search_synonyms')->whereIn('term', array_keys(self::TERM_ALIASES))->delete();
    }

    private function appendAliases(string $table, string $slug, array $aliases): void
    {
        $row = DB::table($table)->where('slug', $slug)->first(['id', 'search_aliases']);
        if (! $row) {
            return;
        }

        $current = array_filter(array_map('trim', explode(',', (string) $row->search_aliases)));
        $merged = array_values(array_unique([...$current, ...$aliases]));

        DB::table($table)->where('id', $row->id)->update(['search_aliases' => implode(', ', $merged)]);
    }
};
