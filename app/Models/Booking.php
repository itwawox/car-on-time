<?php

namespace App\Models;

use App\Jobs\PushToBitrix24;
use App\Jobs\SendBookingSms;
use App\Support\Availability;
use App\Support\CustomerSession;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable(['consent_at',
    'car_id', 'partner_id', 'customer_name', 'phone', 'telegram', 'max', 'whatsapp',
    'starts_at', 'ends_at', 'pickup_location_id', 'return_location_id',
    'extras', 'promo_code', 'quote_snapshot', 'total', 'discount', 'prepaid_amount', 'prepaid_at', 'status',
    'documents_consent_at', 'documents_uploaded_at', 'documents_deleted_at',
    'pickup_mileage', 'pickup_fuel', 'return_mileage', 'return_fuel', 'inspection_notes', 'source', 'utm', 'notes', 'quiz_answers',
])]
class Booking extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const STATUSES = [
        'new' => 'Новая',
        'checking' => 'Уточняем у партнёра',
        'offered' => 'Предложена клиенту',
        'confirmed' => 'Подтверждена',
        'declined' => 'Отказ',
        'alternative' => 'Альтернатива',
    ];

    /** Статусы «в работе»: менеджер уже ответил, но аренда ещё не подтверждена. */
    public const IN_PROGRESS = ['checking', 'offered', 'alternative'];

    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            $booking->public_token ??= Str::lower(Str::random(12));
        });

        static::saving(function (Booking $booking) {
            if ($booking->isDirty('phone') || ! $booking->phone_digits) {
                $booking->phone_digits = CustomerSession::digits((string) $booking->phone);
            }
        });

        static::created(function (Booking $booking) {
            $booking->events()->create(['type' => 'created', 'to' => $booking->status, 'user_id' => auth()->id()]);
            Availability::syncBlockFor($booking);
            PushToBitrix24::dispatchIfEnabled($booking);
            SendBookingSms::dispatchIfEnabled($booking, 'created');
        });

        static::updating(function (Booking $booking) {
            if (! $booking->isDirty('status')) {
                return;
            }
            if ($booking->getOriginal('status') === 'new' && ! $booking->first_response_at) {
                $booking->first_response_at = now();
            }
            if ($booking->status === 'confirmed') {
                $booking->confirmed_at = now();
            }
        });

        static::updated(function (Booking $booking) {
            if ($booking->wasChanged('status')) {
                $booking->events()->create([
                    'type' => 'status',
                    'from' => $booking->getOriginal('status'),
                    'to' => $booking->status,
                    'user_id' => auth()->id(),
                ]);
                PushToBitrix24::dispatchIfEnabled($booking);
                if (in_array($booking->status, ['confirmed', 'declined'], true)) {
                    SendBookingSms::dispatchIfEnabled($booking, $booking->status);
                }
            }
            if ($booking->wasChanged(['status', 'car_id', 'starts_at', 'ends_at'])) {
                Availability::syncBlockFor($booking);
            }
        });
    }

    /** Какие документы просим загрузить заранее. */
    public const DOCUMENTS = [
        'passport' => 'Паспорт — разворот с фото',
        'registration' => 'Паспорт — страница с регистрацией',
        'license_front' => 'Права — лицевая сторона',
        'license_back' => 'Права — оборот',
    ];

    public function registerMediaCollections(): void
    {
        // Документы клиента — только в закрытом хранилище, без публичных ссылок; удаляются после аренды
        $this->addMediaCollection('documents')->useDisk('local');
        // Акт осмотра: фото машины при выдаче и возврате — клиент видит их на странице заявки
        $this->addMediaCollection('act_pickup')->useDisk('local');
        $this->addMediaCollection('act_return')->useDisk('local');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<BookingEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(BookingEvent::class)->latest('created_at')->latest('id');
    }

    /** Сколько минут заявка ждёт первого ответа (null — уже ответили). */
    public function waitingMinutes(): ?int
    {
        if ($this->status !== 'new' || $this->first_response_at) {
            return null;
        }

        return (int) $this->created_at?->diffInMinutes(now());
    }

    /** Короткая ссылка для клиента: статус заявки без входа и без номера в адресе. */
    public function statusUrl(): string
    {
        return route('booking.status', $this->public_token);
    }

    /**
     * Этап для клиента на странице статуса.
     *
     * @return 'received'|'checking'|'confirmed'|'active'|'done'|'declined'
     */
    public function publicStage(): string
    {
        return match (true) {
            $this->status === 'declined' => 'declined',
            $this->status === 'confirmed' && $this->ends_at?->isPast() => 'done',
            $this->status === 'confirmed' && $this->starts_at?->isPast() => 'active',
            $this->status === 'confirmed' => 'confirmed',
            in_array($this->status, self::IN_PROGRESS, true) => 'checking',
            default => 'received',
        };
    }

    public static function slaMinutes(): int
    {
        return max(1, (int) Setting::get('booking_sla_minutes', 15));
    }

    protected function casts(): array
    {
        return [
            'telegram' => 'boolean',
            'max' => 'boolean',
            'whatsapp' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'extras' => 'array',
            'quote_snapshot' => 'array',
            'utm' => 'array',
            'first_response_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'escalated_at' => 'datetime',
            'prepaid_at' => 'datetime',
            'documents_consent_at' => 'datetime',
            'documents_uploaded_at' => 'datetime',
            'documents_deleted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Car, $this> */
    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    /** @return BelongsTo<Partner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function pickupLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'pickup_location_id');
    }

    /** @return BelongsTo<Location, $this> */
    public function returnLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'return_location_id');
    }
}
