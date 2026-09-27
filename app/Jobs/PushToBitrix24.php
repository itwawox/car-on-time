<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Models\Lead;
use App\Services\Bitrix24\Bitrix24Exception;
use App\Services\Bitrix24\Bitrix24Settings;
use App\Services\Bitrix24\Bitrix24Sync;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/** Отправка брони или обращения в Битрикс24. Повторы — только при временных сбоях портала. */
class PushToBitrix24 implements ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    public int $timeout = 60;

    public function __construct(public Booking|Lead $subject) {}

    /** Включена ли интеграция — проверяем до постановки в очередь, чтобы не копить пустые задачи. */
    public static function dispatchIfEnabled(Booking|Lead $subject): void
    {
        if (! Bitrix24Settings::enabled()) {
            return;
        }

        // Заявка клиента не должна зависеть от CRM: даже при синхронной очереди сбой только в журнал
        rescue(fn () => app(Dispatcher::class)->dispatch((new self($subject))->afterCommit()));
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120, 600, 1800, 3600];
    }

    /** Одна запись — одна задача за раз: иначе «создать» и «сменить стадию» создадут две сделки. */
    public function middleware(): array
    {
        return [(new WithoutOverlapping(class_basename($this->subject).':'.$this->subject->getKey()))->releaseAfter(15)->expireAfter(120)];
    }

    public function handle(): void
    {
        $sync = Bitrix24Sync::make();
        if (! $sync || ! Bitrix24Settings::enabled()) {
            return;
        }

        $this->subject->refresh();

        try {
            $this->subject instanceof Booking ? $sync->pushBooking($this->subject) : $sync->pushLead($this->subject);
        } catch (Bitrix24Exception $e) {
            if (! $e->transient) {
                // Ошибка настроек (неверное поле, нет прав): повтор не поможет, видно в журнале обмена
                $this->fail($e);

                return;
            }

            throw $e;
        }
    }
}
