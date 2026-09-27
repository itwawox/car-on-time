<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Lead;
use App\Models\Review;
use App\Models\Setting;
use App\Support\Attribution;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramNotifier
{
    public function bookingCreated(Booking $booking): void
    {
        $this->send($this->bookingText($booking), ['booking' => $booking->id]);
    }

    public function bookingText(Booking $booking): string
    {
        $booking->loadMissing('car', 'pickupLocation', 'returnLocation');

        return implode("\n", array_filter([
            'Новая заявка №'.$booking->id,
            'Авто: '.($booking->car?->name ?? '—'),
            'Телефон: '.$booking->phone,
            $booking->customer_name ? 'Имя: '.$booking->customer_name : null,
            'Даты: '.$booking->starts_at?->format('d.m H:i').' — '.$booking->ends_at?->format('d.m H:i'),
            'Выдача: '.($booking->pickupLocation?->name ?? '—'),
            $booking->returnLocation && $booking->returnLocation->isNot($booking->pickupLocation) ? 'Возврат: '.$booking->returnLocation->name : null,
            $booking->extras ? 'Доп. услуги: '.implode(', ', array_column($booking->extras, 'name')) : null,
            $booking->promo_code ? 'Промокод: '.$booking->promo_code.($booking->discount ? ' (скидка '.number_format((int) $booking->discount, 0, ',', ' ').' ₽)' : ' — не подошёл') : null,
            'Итого: '.number_format((int) $booking->total, 0, ',', ' ').' ₽',
            'Источник: '.$booking->source,
            ($traffic = Attribution::label($booking->utm)) ? 'Трафик: '.$traffic : null,
        ]));
    }

    /** Клиент заполнил необязательный второй шаг на странице «Заявка принята». */
    public function bookingDetailsUpdated(Booking $booking, bool $prefersCall = false): void
    {
        $contact = array_keys(array_filter(['звонок' => $prefersCall, 'WhatsApp' => $booking->whatsapp, 'Telegram' => $booking->telegram, 'MAX' => $booking->max]));

        $this->send(implode("\n", array_filter([
            'Клиент дополнил заявку №'.$booking->id,
            $booking->customer_name ? 'Имя: '.$booking->customer_name : null,
            $contact ? 'Удобно: '.implode(', ', $contact) : null,
            $booking->extras ? 'Доп. услуги: '.implode(', ', array_column($booking->extras, 'name')) : null,
            $booking->promo_code ? 'Промокод: '.$booking->promo_code.($booking->discount ? ' (скидка '.number_format((int) $booking->discount, 0, ',', ' ').' ₽)' : ' — не подошёл') : null,
            'Итого: '.number_format((int) $booking->total, 0, ',', ' ').' ₽',
        ])), ['booking' => $booking->id]);
    }

    public function reviewCreated(Review $review): void
    {
        $review->loadMissing('car');
        $this->send(implode("\n", array_filter([
            'Новый отзыв на модерации',
            str_repeat('★', $review->rating).str_repeat('☆', 5 - $review->rating),
            'Автор: '.$review->author.($review->city ? ', '.$review->city : ''),
            $review->phone ? 'Телефон: '.$review->phone : null,
            $review->car ? 'Авто: '.$review->car->name : null,
            mb_strimwidth($review->body, 0, 600, '…'),
        ])), ['review' => $review->id]);
    }

    public function leadCreated(Lead $lead): void
    {
        $this->send($this->leadText($lead), ['lead' => $lead->id]);
    }

    public function leadText(Lead $lead): string
    {
        return implode("\n", array_filter([
            ['corporate' => 'Заявка от компании', 'owner' => 'Хотят сдать авто'][$lead->type] ?? 'Просят перезвонить',
            'Телефон: '.$lead->phone,
            $lead->name ? 'Имя: '.$lead->name : null,
            $lead->company ? 'Компания: '.$lead->company.($lead->inn ? ', ИНН '.$lead->inn : '') : null,
            ...$lead->detailLines(),
            $lead->message ? mb_strimwidth($lead->message, 0, 600, '…') : null,
            $lead->page ? 'Страница: '.$lead->page : null,
            ($traffic = Attribution::label($lead->utm)) ? 'Трафик: '.$traffic : null,
        ]));
    }

    /** Включён выключатель (по умолчанию — да, как было до его появления) и заполнены бот и чат. */
    public static function enabled(): bool
    {
        return (bool) Setting::get('telegram_enabled', true) && filled(Setting::get('telegram_bot_token')) && filled(Setting::get('telegram_chat_id'));
    }

    /** Сообщение в рабочий чат; без настроек бота или с выключателем «выкл» — тихо ничего не делает. */
    public function send(string $text, array $context = []): void
    {
        if (! self::enabled()) {
            return;
        }
        $token = Setting::get('telegram_bot_token');
        $chat = Setting::get('telegram_chat_id');

        try {
            Http::timeout(8)->post('https://api.telegram.org/bot'.$token.'/sendMessage', ['chat_id' => $chat, 'text' => $text]);
        } catch (Throwable $e) {
            Log::warning('telegram_notify_failed', [...$context, 'error' => $e->getMessage()]);
        }
    }
}
