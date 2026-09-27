<?php

namespace App\Console\Commands;

use App\Jobs\SendBookingSms;
use App\Models\Booking;
use App\Services\Sms\SmsSettings;
use Illuminate\Console\Command;

class SendBookingReminders extends Command
{
    protected $signature = 'bookings:remind';

    protected $description = 'SMS клиентам: напоминание за сутки до выдачи и просьба об отзыве после возврата';

    public function handle(): int
    {
        if (! SmsSettings::enabled()) {
            return self::SUCCESS;
        }

        $reminders = Booking::query()->where('status', 'confirmed')->whereNull('reminded_at')
            ->whereBetween('starts_at', [now()->addHours(20), now()->addHours(28)])->get();
        foreach ($reminders as $booking) {
            $booking->forceFill(['reminded_at' => now()])->saveQuietly();
            SendBookingSms::dispatchIfEnabled($booking, 'reminder');
        }

        $reviews = Booking::query()->where('status', 'confirmed')->whereNull('review_requested_at')
            ->whereBetween('ends_at', [now()->subHours(26), now()->subHours(2)])->get();
        foreach ($reviews as $booking) {
            $booking->forceFill(['review_requested_at' => now()])->saveQuietly();
            SendBookingSms::dispatchIfEnabled($booking, 'review');
        }

        $this->info('Напоминаний: '.$reminders->count().', просьб об отзыве: '.$reviews->count());

        return self::SUCCESS;
    }
}
