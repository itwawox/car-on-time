<?php

namespace App\Services\Payments;

use App\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/** API ЮKassa v3: создание платежа и проверка его статуса. */
class YooKassaClient
{
    private const BASE = 'https://api.yookassa.ru/v3/';

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createPayment(array $payload, ?string $idempotenceKey = null): array
    {
        return $this->request('post', 'payments', $payload, $idempotenceKey ?? (string) Str::uuid());
    }

    /** @return array<string, mixed> */
    public function getPayment(string $id): array
    {
        return $this->request('get', 'payments/'.rawurlencode($id));
    }

    /** Проверка ключей: запрос информации о магазине. @return array<string, mixed> */
    public function me(): array
    {
        return $this->request('get', 'me');
    }

    /** @return array<string, mixed> */
    private function request(string $method, string $path, array $payload = [], ?string $idempotenceKey = null): array
    {
        $http = Http::connectTimeout(5)->timeout(20)->acceptJson()
            ->withBasicAuth((string) Setting::get('yookassa_shop_id'), (string) Setting::secret('yookassa_secret_key'));
        if ($idempotenceKey) {
            $http = $http->withHeaders(['Idempotence-Key' => $idempotenceKey]);
        }

        try {
            $response = $method === 'post' ? $http->asJson()->post(self::BASE.$path, $payload) : $http->get(self::BASE.$path);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Нет связи с ЮKassa: '.$e->getMessage());
        }

        if ($response->failed()) {
            throw new RuntimeException('ЮKassa: '.($response->json('description') ?? 'HTTP '.$response->status()));
        }

        return (array) $response->json();
    }
}
