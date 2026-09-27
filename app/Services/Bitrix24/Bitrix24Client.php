<?php

namespace App\Services\Bitrix24;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Вызовы REST API Битрикс24 через входящий вебхук. */
class Bitrix24Client
{
    /** Ошибки портала, после которых запрос стоит повторить. */
    private const TRANSIENT_ERRORS = ['QUERY_LIMIT_EXCEEDED', 'INTERNAL_SERVER_ERROR', 'OPERATION_TIME_LIMIT'];

    public function __construct(private string $webhookUrl) {}

    public static function fromSettings(): ?self
    {
        $url = Bitrix24Settings::webhookUrl();

        return $url ? new self($url) : null;
    }

    /**
     * @param  array<string, mixed>  $params
     *
     * @throws Bitrix24Exception
     */
    public function call(string $method, array $params = []): mixed
    {
        try {
            $response = Http::connectTimeout(5)->timeout(15)->acceptJson()->asJson()
                ->post(rtrim($this->webhookUrl, '/').'/'.$method.'.json', $params);
        } catch (ConnectionException $e) {
            throw new Bitrix24Exception('Нет связи с порталом: '.$e->getMessage(), transient: true);
        }

        $body = $response->json();
        if (is_array($body) && isset($body['error'])) {
            $code = (string) $body['error'];
            $message = trim($code.' '.($body['error_description'] ?? ''));

            throw new Bitrix24Exception($message, transient: in_array($code, self::TRANSIENT_ERRORS, true) || $response->serverError(), response: $body);
        }

        if ($response->failed() || ! is_array($body) || ! array_key_exists('result', $body)) {
            throw new Bitrix24Exception('Портал ответил HTTP '.$response->status(), transient: $response->serverError() || $response->status() === 429, response: is_array($body) ? $body : null);
        }

        return $body['result'];
    }
}
