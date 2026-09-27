<?php

use App\Models\IntegrationLog;
use App\Models\SearchQuery;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Журнал поисковых запросов: старые записи без пользы для словарей
Schedule::call(fn () => SearchQuery::query()->where('last_searched_at', '<', now()->subDays(180))->delete())
    ->weekly()
    ->name('search-queries:prune');

// Заявки без ответа дольше срока — напоминание в рабочий чат
Schedule::command('bookings:escalate')->everyMinute()->withoutOverlapping();

// SMS клиентам: напоминание за сутки и просьба об отзыве (днём, чтобы не будить)
Schedule::command('bookings:remind')->hourlyAt(5)->between('9:00', '21:00');

// Документы клиентов (паспорт, права) — удаляем после аренды по сроку из настроек
Schedule::command('documents:prune')->dailyAt('04:10');

// Резервная копия базы каждую ночь; хранятся последние 14
Schedule::command('backup:database')->dailyAt('03:30')->withoutOverlapping();

// Очередь без отдельного supervisor: CRM, SMS и прочие внешние вызовы разбираются каждую минуту
Schedule::command('queue:work --stop-when-empty --max-time=55 --sleep=3')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->name('queue:drain');

// Журнал обмена с внешними системами: хватает квартала для разборов
Schedule::call(fn () => IntegrationLog::query()->where('created_at', '<', now()->subDays(90))->delete())
    ->daily()
    ->name('integration-logs:prune');
