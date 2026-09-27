<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Models\IntegrationLog;
use App\Models\Setting;
use App\Services\Sms\SmsException;
use App\Services\Sms\SmsSender;
use App\Services\Sms\SmsSettings;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** SMS клиенту по событию заявки. Не повторяется: второе одинаковое SMS хуже, чем ни одного. */
class SendBookingSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public Booking $booking, public string $template) {}

    public static function dispatchIfEnabled(Booking $booking, string $template): void
    {
        if (! SmsSettings::enabled() || ! SmsSettings::eventEnabled($template)) {
            return;
        }

        rescue(fn () => app(Dispatcher::class)->dispatch((new self($booking, $template))->afterCommit()));
    }

    public function handle(SmsSender $sender): void
    {
        $text = self::render($this->booking, $this->template);
        $log = new IntegrationLog(['integration' => 'sms', 'event' => 'sms.'.$this->template, 'request' => ['to' => $this->booking->phone, 'text' => $text]]);
        $log->subject()->associate($this->booking);
        $started = hrtime(true);

        try {
            $log->fill(['status' => 'success', 'response' => $sender->send($this->booking->phone, $text)]);
            $this->booking->events()->create(['type' => 'notified', 'comment' => 'SMS «'.SmsSettings::TEMPLATES[$this->template]['label'].'»']);
        } catch (SmsException $e) {
            $log->fill(['status' => 'failed', 'error' => $e->getMessage(), 'response' => $e->response]);
        } finally {
            $log->duration_ms = (int) ((hrtime(true) - $started) / 1_000_000);
            $log->save();
        }
    }

    /** Текст SMS по шаблону из админки с подстановками. */
    public static function render(Booking $booking, string $template): string
    {
        $booking->loadMissing('car', 'pickupLocation');

        return trim(strtr(SmsSettings::template($template), [
            '{brand}' => (string) Setting::get('brand_name', 'Car on Time'),
            '{id}' => (string) $booking->id,
            '{car}' => (string) ($booking->car?->displayName() ?? 'авто'),
            '{date}' => (string) $booking->starts_at?->translatedFormat('j F'),
            '{time}' => (string) $booking->starts_at?->format('H:i'),
            '{place}' => (string) ($booking->pickupLocation?->name ?? ''),
            '{total}' => number_format((int) $booking->total, 0, ',', ' ').' ₽',
            '{phone}' => (string) Setting::get('phone'),
            '{link}' => $booking->statusUrl(),
            '{review_link}' => route('reviews').'#review-form',
        ]));
    }
}
