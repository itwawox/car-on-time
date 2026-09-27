<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Раздел «Вопросы и ответы» уровня крупных прокатов: разделы, ссылки из ответов, отметки
 * «на главной» и «в карточке машины». Ответы — по условиям аренды и договору (database/data/faq.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('id');
            $table->string('link_url')->nullable()->after('answer');
            $table->string('link_label')->nullable()->after('link_url');
            $table->boolean('is_featured')->default(false)->after('group');
            $table->boolean('show_on_car')->default(false)->after('is_featured');
            $table->boolean('is_published')->default(true)->after('show_on_car');
        });

        DB::table('faqs')->delete();
        $now = now();
        foreach (require database_path('data/faq.php') as $i => $f) {
            DB::table('faqs')->insert([
                'slug' => $f['slug'],
                'question' => $f['q'],
                'answer' => $f['a'],
                'link_url' => $f['link'][0] ?? null,
                'link_label' => $f['link'][1] ?? null,
                'group' => $f['group'],
                'is_featured' => $f['featured'] ?? false,
                'show_on_car' => $f['car'] ?? false,
                'is_published' => true,
                'sort' => ($i + 1) * 10,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Способы оплаты — как на странице «Условия аренды»
        if (! Setting::get('payment_methods')) {
            Setting::put('payment_methods', "Наличные\nБанковский перевод\nОнлайн-банк");
        }
        Cache::forget('sitemap');
    }

    public function down(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'link_url', 'link_label', 'is_featured', 'show_on_car', 'is_published']);
        });
    }
};
