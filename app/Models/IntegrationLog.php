<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Журнал обмена с внешними системами: что отправили, что ответили, сколько заняло. */
#[Fillable(['integration', 'event', 'subject_type', 'subject_id', 'status', 'request', 'response', 'error', 'duration_ms'])]
class IntegrationLog extends Model
{
    public const INTEGRATIONS = ['bitrix24' => 'Битрикс24', 'sms' => 'SMS', 'telegram' => 'Telegram', 'payments' => 'Оплата'];

    public const STATUSES = ['success' => 'Успешно', 'failed' => 'Ошибка', 'skipped' => 'Пропущено'];

    protected function casts(): array
    {
        return [
            'request' => 'array',
            'response' => 'array',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
