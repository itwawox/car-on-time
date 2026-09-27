<?php

namespace App\Services\Sms;

use App\Models\Setting;
use App\Support\Phone;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Отправка SMS через выбранного в админке провайдера. */
class SmsSender
{
    /**
     * @return array<string, mixed> ответ провайдера
     *
     * @throws SmsException
     */
    public function send(string $phone, string $text): array
    {
        $to = ltrim(Phone::e164($phone), '+');
        $sender = Setting::get('sms_sender') ?: null;

        try {
            return SmsSettings::provider() === 'smsc' ? $this->smsc($to, $text, $sender) : $this->smsru($to, $text, $sender);
        } catch (ConnectionException $e) {
            throw new SmsException('Нет связи с SMS-провайдером: '.$e->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function smsru(string $to, string $text, ?string $sender): array
    {
        $body = Http::connectTimeout(5)->timeout(15)->asForm()->post('https://sms.ru/sms/send', array_filter([
            'api_id' => Setting::secret('sms_api_key'),
            'to' => $to,
            'msg' => $text,
            'from' => $sender,
            'json' => 1,
        ]))->json() ?? [];

        $status = $body['sms'][$to]['status'] ?? $body['status'] ?? null;
        if ($status !== 'OK') {
            throw new SmsException('SMS.ru: '.($body['sms'][$to]['status_text'] ?? $body['status_text'] ?? 'неизвестная ошибка'), $body);
        }

        return $body;
    }

    /** @return array<string, mixed> */
    private function smsc(string $to, string $text, ?string $sender): array
    {
        $body = Http::connectTimeout(5)->timeout(15)->asForm()->post('https://smsc.ru/sys/send.php', array_filter([
            'login' => Setting::get('sms_login'),
            'psw' => Setting::secret('sms_password'),
            'phones' => $to,
            'mes' => $text,
            'sender' => $sender,
            'charset' => 'utf-8',
            'fmt' => 3,
        ]))->json() ?? [];

        if (isset($body['error']) || ! isset($body['id'])) {
            throw new SmsException('SMSC: '.($body['error'] ?? 'неизвестная ошибка'), $body);
        }

        return $body;
    }
}
