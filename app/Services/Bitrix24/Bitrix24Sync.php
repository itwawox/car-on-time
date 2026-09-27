<?php

namespace App\Services\Bitrix24;

use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Leads\LeadResource;
use App\Models\Booking;
use App\Models\IntegrationLog;
use App\Models\Lead;
use App\Services\TelegramNotifier;
use App\Support\Phone;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Бронь и обращения сайта → CRM Битрикс24.
 * Повторный вызов безопасен: если у записи уже есть crm_id, обновляется стадия, а не создаётся дубль.
 */
class Bitrix24Sync
{
    public function __construct(private Bitrix24Client $client) {}

    public static function make(): ?self
    {
        $client = Bitrix24Client::fromSettings();

        return $client ? new self($client) : null;
    }

    /** @throws Bitrix24Exception */
    public function pushBooking(Booking $booking): void
    {
        $booking->loadMissing('car.classes', 'pickupLocation', 'returnLocation');

        if ($booking->crm_id) {
            $this->updateBookingStage($booking);

            return;
        }

        $entity = Bitrix24Settings::bookingEntity();
        $fields = $this->bookingFields($booking, $entity);

        $id = $this->logged('booking.created', $booking, 'crm.'.$entity.'.add', fn () => [
            'fields' => $entity === 'deal' ? [...$fields, 'CONTACT_ID' => $this->contactId($booking->phone, $booking->customer_name)] : $fields,
            'params' => ['REGISTER_SONET_EVENT' => 'Y'],
        ]);

        $booking->forceFill(['crm_entity' => $entity, 'crm_id' => (string) $id])->saveQuietly();
    }

    /** @throws Bitrix24Exception */
    public function pushLead(Lead $lead): void
    {
        if ($lead->crm_id) {
            return;
        }

        $entity = Bitrix24Settings::leadEntity();
        $title = (Lead::TYPES[$lead->type] ?? 'Обращение').' с сайта №'.$lead->id;
        $fields = array_filter([
            'TITLE' => $title,
            'ASSIGNED_BY_ID' => Bitrix24Settings::assignedById(),
            'SOURCE_ID' => Bitrix24Settings::sourceId(),
            'SOURCE_DESCRIPTION' => 'Сайт: '.($lead->page ?: config('app.url')),
            'COMMENTS' => nl2br(e(app(TelegramNotifier::class)->leadText($lead))),
            'ORIGINATOR_ID' => 'car-on-time',
            'ORIGIN_ID' => 'lead-'.$lead->id,
        ], fn ($v) => $v !== null && $v !== '');

        if ($entity === 'deal') {
            $fields['CATEGORY_ID'] = Bitrix24Settings::categoryId();
            if ($lead->company) {
                $fields['TITLE'] .= ' — '.$lead->company;
            }
        } else {
            $fields += array_filter([
                'NAME' => $lead->name,
                'COMPANY_TITLE' => $lead->company,
                'PHONE' => [['VALUE' => self::phone($lead->phone), 'VALUE_TYPE' => 'WORK']],
            ]);
        }

        $fields += $this->customFields('lead', $this->leadValues($lead));
        $fields += self::utmFields($lead->utm);

        $id = $this->logged('lead.created', $lead, 'crm.'.$entity.'.add', fn () => [
            'fields' => $entity === 'deal' ? [...$fields, 'CONTACT_ID' => $this->contactId($lead->phone, $lead->name)] : $fields,
        ]);

        $lead->forceFill(['crm_entity' => $entity, 'crm_id' => (string) $id])->saveQuietly();
    }

    /** @throws Bitrix24Exception */
    private function updateBookingStage(Booking $booking): void
    {
        $stage = Bitrix24Settings::stageFor((string) $booking->status);
        if (! $stage) {
            return;
        }

        $field = $booking->crm_entity === 'lead' ? 'STATUS_ID' : 'STAGE_ID';
        $this->logged('booking.status', $booking, 'crm.'.($booking->crm_entity ?: 'deal').'.update', [
            'id' => (int) $booking->crm_id,
            'fields' => [$field => $stage],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingFields(Booking $booking, string $entity): array
    {
        $title = 'Бронь №'.$booking->id.': '.($booking->car?->name ?? 'авто');
        $stage = Bitrix24Settings::stageFor((string) $booking->status);

        $fields = [
            'TITLE' => $title,
            'OPPORTUNITY' => (int) $booking->total,
            'CURRENCY_ID' => 'RUB',
            'ASSIGNED_BY_ID' => Bitrix24Settings::assignedById(),
            'SOURCE_ID' => Bitrix24Settings::sourceId(),
            'SOURCE_DESCRIPTION' => 'Сайт '.config('app.url'),
            'COMMENTS' => nl2br(e(app(TelegramNotifier::class)->bookingText($booking))),
            'ORIGINATOR_ID' => 'car-on-time',
            'ORIGIN_ID' => 'booking-'.$booking->id,
        ];

        if ($entity === 'deal') {
            $fields += [
                'CATEGORY_ID' => Bitrix24Settings::categoryId(),
                'STAGE_ID' => $stage,
                'BEGINDATE' => $booking->starts_at?->toAtomString(),
                'CLOSEDATE' => $booking->ends_at?->toAtomString(),
            ];
        } else {
            $fields += [
                'STATUS_ID' => $stage,
                'NAME' => $booking->customer_name,
                'PHONE' => [['VALUE' => self::phone($booking->phone), 'VALUE_TYPE' => 'MOBILE']],
            ];
        }

        $fields += $this->customFields('booking', $this->bookingValues($booking));
        $fields += self::utmFields($booking->utm);

        return array_filter($fields, fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @return array<string, scalar|null>
     */
    public function bookingValues(Booking $booking): array
    {
        $snapshot = (array) $booking->quote_snapshot;

        return [
            'booking_id' => $booking->id,
            'car' => $booking->car?->name,
            'car_class' => $booking->car?->classes->pluck('name')->implode(', '),
            'starts_at' => $booking->starts_at?->toAtomString(),
            'ends_at' => $booking->ends_at?->toAtomString(),
            'days' => $snapshot['days'] ?? null,
            'pickup' => $booking->pickupLocation?->name,
            'return' => $booking->returnLocation?->name,
            'extras' => $booking->extras ? implode(', ', array_column($booking->extras, 'name')) : null,
            'promo_code' => $booking->promo_code,
            'total' => (int) $booking->total,
            'deposit' => isset($snapshot['deposit']) ? (int) $snapshot['deposit'] : null,
            'messengers' => implode(', ', array_keys(array_filter(['Telegram' => $booking->telegram, 'MAX' => $booking->max, 'WhatsApp' => $booking->whatsapp]))),
            'source' => $booking->source,
            'admin_url' => BookingResource::getUrl('edit', ['record' => $booking], panel: 'admin'),
        ];
    }

    /**
     * @return array<string, scalar|null>
     */
    private function leadValues(Lead $lead): array
    {
        return [
            'lead_id' => $lead->id,
            'type' => Lead::TYPES[$lead->type] ?? $lead->type,
            'company' => $lead->company,
            'inn' => $lead->inn,
            'message' => $lead->message,
            'details' => implode('; ', $lead->detailLines()),
            'page' => $lead->page,
            'admin_url' => LeadResource::getUrl('edit', ['record' => $lead], panel: 'admin'),
        ];
    }

    /**
     * @param  array<string, scalar|null>  $values
     * @return array<string, scalar>
     */
    private function customFields(string $kind, array $values): array
    {
        $fields = [];
        foreach (Bitrix24Settings::fieldMap($kind) as $site => $crm) {
            if (isset($values[$site]) && $values[$site] !== '') {
                $fields[$crm] = $values[$site];
            }
        }

        return $fields;
    }

    /**
     * Контакт по телефону: находим существующий, чтобы не плодить дубли, иначе создаём.
     *
     * @throws Bitrix24Exception
     */
    private function contactId(string $phone, ?string $name): int
    {
        $phone = self::phone($phone);
        $found = $this->client->call('crm.duplicate.findbycomm', ['entity_type' => 'CONTACT', 'type' => 'PHONE', 'values' => [$phone]]);
        if (is_array($found) && ! empty($found['CONTACT'])) {
            return (int) $found['CONTACT'][0];
        }

        return (int) $this->client->call('crm.contact.add', ['fields' => array_filter([
            'NAME' => $name ?: 'Клиент с сайта',
            'PHONE' => [['VALUE' => $phone, 'VALUE_TYPE' => 'MOBILE']],
            'SOURCE_ID' => Bitrix24Settings::sourceId(),
            'ASSIGNED_BY_ID' => Bitrix24Settings::assignedById(),
        ])]);
    }

    /**
     * Вызов с записью в журнал обмена. Параметры можно передать замыканием: тогда подготовительные
     * запросы (поиск контакта) тоже попадают в журнал, если портал упадёт уже на них.
     *
     * @param  array<string, mixed>|Closure(): array<string, mixed>  $params
     *
     * @throws Bitrix24Exception
     */
    private function logged(string $event, Model $subject, string $method, array|Closure $params): mixed
    {
        $started = hrtime(true);
        $log = new IntegrationLog([
            'integration' => 'bitrix24',
            'event' => $event,
            'request' => ['method' => $method],
        ]);
        $log->subject()->associate($subject);

        try {
            $params = $params instanceof Closure ? $params() : $params;
            $log->request = ['method' => $method, 'params' => $params];
            $result = $this->client->call($method, $params);
            $log->fill(['status' => 'success', 'response' => ['result' => $result]]);

            return $result;
        } catch (Throwable $e) {
            $log->fill([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'response' => $e instanceof Bitrix24Exception ? $e->response : null,
            ]);

            throw $e;
        } finally {
            $log->duration_ms = (int) ((hrtime(true) - $started) / 1_000_000);
            $log->save();
        }
    }

    /**
     * Стандартные UTM-поля сделки и лида в Битрикс24 — по ним CRM строит отчёты по рекламе.
     *
     * @param  array<string, string>|null  $utm
     * @return array<string, string>
     */
    private static function utmFields(?array $utm): array
    {
        return collect(['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'])
            ->filter(fn (string $key) => filled($utm[$key] ?? null))
            ->mapWithKeys(fn (string $key) => [strtoupper($key) => (string) $utm[$key]])
            ->all();
    }

    /** Телефон в виде +79781234567 — так CRM находит дубли. */
    public static function phone(string $phone): string
    {
        return Phone::e164($phone);
    }
}
