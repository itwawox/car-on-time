<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\Bitrix24\Bitrix24Settings;
use App\Services\Payments\PaymentSettings;
use App\Services\Sms\SmsSettings;
use App\Services\TelegramNotifier;
use Illuminate\Console\Command;

class IntegrationsStatus extends Command
{
    protected $signature = 'integrations:status';

    protected $description = 'Какие интеграции включены на этом сайте (Битрикс24, SMS, ЮKassa, Telegram, Метрика)';

    public function handle(): int
    {
        $row = fn (string $name, bool $switch, bool $keys, bool $works) => [
            $name,
            $switch ? 'вкл' : 'выкл',
            $keys ? 'есть' : 'нет',
            $works ? '✅ работает' : '— не отправляет',
        ];

        $this->table(['Интеграция', 'Выключатель', 'Ключи', 'Итог'], [
            $row('Битрикс24', (bool) Setting::get('bitrix_enabled'), Bitrix24Settings::webhookUrl() !== null, Bitrix24Settings::enabled()),
            $row('SMS клиентам', (bool) Setting::get('sms_enabled'), SmsSettings::credentialsFilled(), SmsSettings::enabled()),
            $row('ЮKassa (предоплата)', (bool) Setting::get('payments_enabled'), filled(Setting::get('yookassa_shop_id')) && filled(Setting::secret('yookassa_secret_key')), PaymentSettings::enabled()),
            $row('Telegram менеджерам', (bool) Setting::get('telegram_enabled', true), filled(Setting::get('telegram_bot_token')) && filled(Setting::get('telegram_chat_id')), TelegramNotifier::enabled()),
            $row('Яндекс Метрика', app()->isProduction(), filled(Setting::get('yandex_metrika_id')), app()->isProduction() && filled(Setting::get('yandex_metrika_id'))),
        ]);

        $this->line('Метрика работает только на боевом сайте (APP_ENV=production) и после согласия посетителя на cookie.');

        return self::SUCCESS;
    }
}
