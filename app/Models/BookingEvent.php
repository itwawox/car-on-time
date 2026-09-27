<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** История заявки: создание, смена статуса, заметки менеджера, эскалации. */
#[Fillable(['booking_id', 'user_id', 'type', 'from', 'to', 'comment'])]
class BookingEvent extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = ['created' => 'Заявка получена', 'status' => 'Статус', 'note' => 'Заметка', 'escalated' => 'Эскалация', 'notified' => 'Уведомление клиенту', 'payment' => 'Оплата'];

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
