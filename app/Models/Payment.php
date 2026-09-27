<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Онлайн-предоплата по заявке. Статус подтверждается только запросом к платёжной системе. */
#[Fillable(['booking_id', 'provider', 'provider_id', 'amount', 'status', 'confirmation_url', 'method', 'paid_at', 'payload'])]
class Payment extends Model
{
    public const STATUSES = ['pending' => 'Ожидает оплаты', 'succeeded' => 'Оплачено', 'canceled' => 'Отменён'];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
