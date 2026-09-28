<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Запуск резервного копирования базы: откуда запущен, чем закончился, какой файл получился. */
#[Fillable(['source', 'status', 'user_id', 'file', 'size', 'duration_ms', 'error', 'started_at'])]
class BackupRun extends Model
{
    public const SOURCES = [
        'schedule' => 'Ночная, по расписанию',
        'deploy' => 'Перед выкладкой',
        'manual' => 'Вручную из админки',
        'console' => 'Из консоли',
        'found' => 'Найдена на диске',
    ];

    public const STATUSES = ['success' => 'Успешно', 'failed' => 'Ошибка'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'size' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
