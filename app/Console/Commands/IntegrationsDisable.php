<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;

class IntegrationsDisable extends Command
{
    protected $signature = 'integrations:disable {--force : Не спрашивать подтверждение}';

    protected $description = 'Выключить все внешние интеграции (Битрикс24, SMS, ЮKassa, Telegram). Ключи не удаляются — включить можно в админке';

    private const SWITCHES = ['bitrix_enabled', 'bitrix_inbound_enabled', 'sms_enabled', 'payments_enabled', 'telegram_enabled'];

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('force')
            && ! $this->confirm('Это БОЕВОЙ сайт: заявки перестанут уходить в CRM, Telegram и SMS. Точно выключить?')) {
            return self::FAILURE;
        }

        foreach (self::SWITCHES as $key) {
            Setting::put($key, false, 'integrations');
        }

        $this->info('Интеграции выключены. Ключи сохранены — включить обратно: админка → Интеграции → Интеграции.');
        $this->call('integrations:status');

        return self::SUCCESS;
    }
}
