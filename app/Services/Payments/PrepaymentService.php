<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\IntegrationLog;
use App\Models\Payment;
use App\Services\TelegramNotifier;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Предоплата по заявке: создать платёж, узнать его статус, зачесть оплату в заявку.
 * Статусу из браузера и из уведомления не доверяем — всегда переспрашиваем ЮKassa.
 */
class PrepaymentService
{
    public function __construct(private YooKassaClient $client, private TelegramNotifier $telegram) {}

    public static function canPrepay(Booking $booking): bool
    {
        return PaymentSettings::enabled()
            && in_array($booking->status, PaymentSettings::PAYABLE_STATUSES, true)
            && ! $booking->prepaid_at
            && ! $booking->starts_at?->isPast()
            && PaymentSettings::amountFor($booking) > 0;
    }

    /** Платёж для оплаты: свежий неоплаченный переиспользуем, иначе создаём новый. */
    public function start(Booking $booking): Payment
    {
        if (! self::canPrepay($booking)) {
            throw new RuntimeException('Предоплата по этой заявке сейчас недоступна.');
        }

        $existing = $booking->payments()->where('status', 'pending')->where('created_at', '>', now()->subMinutes(30))
            ->whereNotNull('confirmation_url')->latest()->first();
        if ($existing && $existing->amount === PaymentSettings::amountFor($booking)) {
            return $existing;
        }

        $amount = PaymentSettings::amountFor($booking);
        $payment = $booking->payments()->create(['amount' => $amount, 'status' => 'pending']);
        $payload = $this->payload($booking, $payment);

        $response = $this->logged('payment.create', $booking, ['amount' => $amount], fn () => $this->client->createPayment($payload, 'payment-'.$payment->id));

        $payment->update([
            'provider_id' => $response['id'] ?? null,
            'status' => $response['status'] ?? 'pending',
            'confirmation_url' => $response['confirmation']['confirmation_url'] ?? null,
            'payload' => $response,
        ]);

        return $payment;
    }

    /** Актуальный статус из ЮKassa; при успехе — зачесть предоплату в заявку (один раз). */
    public function refresh(Payment $payment): Payment
    {
        if (! $payment->provider_id || $payment->status !== 'pending') {
            return $payment;
        }

        $response = $this->logged('payment.check', $payment->booking, ['id' => $payment->provider_id], fn () => $this->client->getPayment($payment->provider_id));
        $status = (string) ($response['status'] ?? 'pending');

        if ($status === 'succeeded') {
            $this->markPaid($payment, $response);
        } elseif ($status === 'canceled') {
            $payment->update(['status' => 'canceled', 'payload' => $response]);
        }

        return $payment->fresh();
    }

    private function markPaid(Payment $payment, array $response): void
    {
        $paid = DB::transaction(function () use ($payment, $response) {
            $locked = Payment::query()->lockForUpdate()->find($payment->id);
            if ($locked->status === 'succeeded') {
                return false;
            }
            $locked->update([
                'status' => 'succeeded',
                'paid_at' => now(),
                'method' => $response['payment_method']['type'] ?? null,
                'payload' => $response,
            ]);
            $booking = $locked->booking;
            $booking->forceFill([
                'prepaid_amount' => (int) $booking->prepaid_amount + $locked->amount,
                'prepaid_at' => $booking->prepaid_at ?? now(),
            ])->saveQuietly();
            $booking->events()->create(['type' => 'payment', 'comment' => 'Предоплата '.number_format($locked->amount, 0, ',', ' ').' ₽ получена онлайн']);

            return true;
        });

        if ($paid) {
            $this->telegram->send('💳 Предоплата по заявке №'.$payment->booking_id.': '.number_format($payment->amount, 0, ',', ' ').' ₽ получена', ['booking' => $payment->booking_id]);
        }
    }

    /** @return array<string, mixed> */
    private function payload(Booking $booking, Payment $payment): array
    {
        $booking->loadMissing('car');
        $value = number_format($payment->amount, 2, '.', '');
        $description = 'Предоплата по заявке №'.$booking->id.' — аренда '.($booking->car?->displayName() ?? 'автомобиля');

        $payload = [
            'amount' => ['value' => $value, 'currency' => 'RUB'],
            'capture' => true,
            'confirmation' => ['type' => 'redirect', 'return_url' => $booking->statusUrl()],
            'description' => mb_substr($description, 0, 128),
            'metadata' => ['booking_id' => (string) $booking->id, 'payment_id' => (string) $payment->id],
        ];

        // Чек по 54-ФЗ — если в магазине ЮKassa подключена онлайн-касса
        if (PaymentSettings::receiptsEnabled()) {
            $payload['receipt'] = [
                'customer' => ['phone' => ltrim(Phone::e164($booking->phone), '+')],
                'items' => [[
                    'description' => mb_substr($description, 0, 128),
                    'quantity' => '1.00',
                    'amount' => ['value' => $value, 'currency' => 'RUB'],
                    'vat_code' => PaymentSettings::vatCode(),
                    'payment_mode' => 'advance',
                    'payment_subject' => 'service',
                ]],
            ];
        }

        return $payload;
    }

    /** @param  callable(): array<string, mixed>  $call */
    private function logged(string $event, Booking $booking, array $request, callable $call): array
    {
        $log = new IntegrationLog(['integration' => 'payments', 'event' => $event, 'request' => $request]);
        $log->subject()->associate($booking);
        $started = hrtime(true);

        try {
            $response = $call();
            $log->fill(['status' => 'success', 'response' => ['id' => $response['id'] ?? null, 'status' => $response['status'] ?? null]]);

            return $response;
        } catch (RuntimeException $e) {
            $log->fill(['status' => 'failed', 'error' => $e->getMessage()]);

            throw $e;
        } finally {
            $log->duration_ms = (int) ((hrtime(true) - $started) / 1_000_000);
            $log->save();
        }
    }
}
