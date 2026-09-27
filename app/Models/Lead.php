<?php

namespace App\Models;

use App\Jobs\PushToBitrix24;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['consent_at', 'type', 'name', 'phone', 'company', 'inn', 'message', 'details', 'page', 'utm', 'status', 'notes'])]
class Lead extends Model
{
    public const TYPES = ['callback' => 'Перезвонить', 'corporate' => 'Юрлицо', 'owner' => 'Сдать авто'];

    public const STATUSES = ['new' => 'Новая', 'in_work' => 'В работе', 'done' => 'Закрыта'];

    /** Заявка «Сдать авто»: кто владелец */
    public const OWNER_KINDS = ['self_employed' => 'Самозанятый', 'ip' => 'ИП', 'company' => 'Прокатная компания', 'person' => 'Частное лицо, статус пока не оформлен'];

    /** Подписи полей details для админки и уведомлений */
    public const DETAIL_LABELS = ['owner_kind' => 'Кто', 'city' => 'Город', 'car' => 'Машина', 'year' => 'Год', 'gearbox' => 'Коробка', 'cars_count' => 'Сколько машин'];

    protected static function booted(): void
    {
        static::created(fn (Lead $lead) => PushToBitrix24::dispatchIfEnabled($lead));
    }

    protected function casts(): array
    {
        return ['details' => 'array', 'utm' => 'array'];
    }

    /** details в виде «Подпись: значение» с человеческими значениями */
    public function detailLines(): array
    {
        $lines = [];
        foreach (self::DETAIL_LABELS as $key => $label) {
            $value = $this->details[$key] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $value = match ($key) {
                'owner_kind' => self::OWNER_KINDS[$value] ?? $value,
                'gearbox' => ['at' => 'Автомат', 'mt' => 'Механика'][$value] ?? $value,
                default => $value,
            };
            $lines[] = $label.': '.$value;
        }

        return $lines;
    }
}
