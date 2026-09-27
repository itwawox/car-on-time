<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\TelegramNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationSwitchesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::put('telegram_bot_token', 'bot');
        Setting::put('telegram_chat_id', '-100');
        Http::fake();
    }

    public function test_telegram_works_by_default_when_bot_is_configured(): void
    {
        app(TelegramNotifier::class)->send('проверка');

        Http::assertSentCount(1);
    }

    public function test_telegram_switch_turns_messages_off(): void
    {
        Setting::put('telegram_enabled', false);

        app(TelegramNotifier::class)->send('проверка');

        Http::assertNothingSent();
    }

    public function test_disable_command_switches_everything_off_but_keeps_keys(): void
    {
        Setting::put('bitrix_enabled', true);
        Setting::put('sms_enabled', true);
        Setting::put('payments_enabled', true);

        $this->artisan('integrations:disable')->assertSuccessful();

        foreach (['bitrix_enabled', 'sms_enabled', 'payments_enabled', 'telegram_enabled'] as $key) {
            $this->assertFalse(Setting::get($key), $key);
        }
        $this->assertSame('bot', Setting::get('telegram_bot_token'));
        $this->artisan('integrations:status')->assertSuccessful()->expectsOutputToContain('Telegram менеджерам');
    }
}
