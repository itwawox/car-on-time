<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Car;
use App\Models\Extra;
use App\Models\Location;
use App\Models\Setting;
use App\Services\Payments\PrepaymentService;
use App\Services\QuoteCalculator;
use App\Services\TelegramNotifier;
use App\Support\Attribution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function store(Request $request, QuoteCalculator $calculator, TelegramNotifier $telegram): RedirectResponse
    {
        $data = $request->validate([
            'car_id' => ['required', 'exists:cars,id'],
            'phone' => ['required', 'string', 'min:10', 'max:20'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'telegram' => ['nullable', 'boolean'],
            'max' => ['nullable', 'boolean'],
            'whatsapp' => ['nullable', 'boolean'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'pickup_location_id' => ['nullable', 'exists:locations,id'],
            'return_location_id' => ['nullable', 'exists:locations,id'],
            'extras' => ['nullable', 'array'],
            'extras.*' => ['integer', 'exists:extras,id'],
            'promo_code' => ['nullable', 'string', 'max:40'],
            'pd_consent' => ['accepted'],
        ], [
            'pd_consent.accepted' => 'Нужно согласие на обработку персональных данных — без него мы не можем принять заявку.',
            'phone.required' => 'Укажите телефон — по нему подтвердим наличие машины.',
            'phone.min' => 'Проверьте номер телефона — не хватает цифр.',
            'ends_at.after' => 'Дата возврата должна быть позже даты выдачи.',
        ]);

        $extras = self::extras($data['extras'] ?? []);

        $car = Car::query()->findOrFail($data['car_id']);
        $pickup = isset($data['pickup_location_id']) ? Location::query()->find($data['pickup_location_id']) : null;
        // «Вернуть в другом месте» не отмечен — возврат там же, где выдача
        $return = isset($data['return_location_id']) ? Location::query()->find($data['return_location_id']) : $pickup;

        $quote = $calculator->quote(
            $car,
            Carbon::parse($data['starts_at']),
            Carbon::parse($data['ends_at']),
            $pickup,
            $return,
            $extras,
            $data['promo_code'] ?? null,
        );

        $booking = Booking::query()->create([
            'car_id' => $car->id,
            'partner_id' => $car->partner_id,
            'customer_name' => $data['customer_name'] ?? null,
            'phone' => $data['phone'],
            'telegram' => (bool) ($data['telegram'] ?? false),
            'max' => (bool) ($data['max'] ?? false),
            'whatsapp' => (bool) ($data['whatsapp'] ?? false),
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'pickup_location_id' => $pickup?->id,
            'return_location_id' => $return?->id,
            'extras' => $extras ?: null,
            'consent_at' => now(),
            'promo_code' => filled($data['promo_code'] ?? null) ? mb_strtoupper(trim($data['promo_code'])) : null,
            'quote_snapshot' => $quote,
            'total' => $quote['total'] ?? 0,
            'discount' => $quote['discount'] ?? 0,
            'status' => 'new',
            'source' => $request->input('source', 'card'),
            'quiz_answers' => $request->input('quiz_answers'),
            'utm' => Attribution::fromRequest($request),
        ]);

        $telegram->bookingCreated($booking);

        // Подписанная ссылка: по номеру заявки нельзя открыть чужие данные
        return redirect()->to(URL::signedRoute('booking.thanks', $booking));
    }

    public function thanks(Booking $booking, PrepaymentService $payments): View
    {
        // Клиент вернулся со страницы оплаты — уточняем статус, не дожидаясь уведомления ЮKassa
        $booking->payments()->where('status', 'pending')->whereNotNull('provider_id')->get()
            ->each(fn ($payment) => rescue(fn () => $payments->refresh($payment)));
        $booking->refresh();

        return view('booking-thanks', [
            'booking' => $booking->load(['car', 'pickupLocation']),
            'calendarUrl' => URL::signedRoute('booking.calendar', $booking),
            'detailsUrl' => URL::signedRoute('booking.details', $booking),
            'extrasList' => Extra::query()->active()->get(),
        ]);
    }

    /**
     * Второй шаг после заявки, необязательный: имя, как связаться, доп. услуги, промокод.
     * Доп. услуги и промокод пересчитывают сумму тем же QuoteCalculator.
     */
    public function details(Request $request, Booking $booking, QuoteCalculator $calculator, TelegramNotifier $telegram): RedirectResponse
    {
        $data = $request->validate([
            'customer_name' => ['nullable', 'string', 'max:120'],
            'contact' => ['nullable', 'array'],
            'contact.*' => ['in:call,whatsapp,telegram,max'],
            'extras' => ['nullable', 'array'],
            'extras.*' => ['integer', 'exists:extras,id'],
            'promo_code' => ['nullable', 'string', 'max:40'],
        ]);

        $contact = $data['contact'] ?? [];
        $extras = self::extras($data['extras'] ?? []);
        $promo = filled($data['promo_code'] ?? null) ? mb_strtoupper(trim($data['promo_code'])) : null;

        $booking->loadMissing('car', 'pickupLocation', 'returnLocation');
        $quote = $booking->car && $booking->starts_at && $booking->ends_at
            ? $calculator->quote($booking->car, $booking->starts_at, $booking->ends_at, $booking->pickupLocation, $booking->returnLocation, $extras, $promo)
            : null;

        $booking->fill([
            'customer_name' => filled($data['customer_name'] ?? null) ? trim($data['customer_name']) : $booking->customer_name,
            'whatsapp' => in_array('whatsapp', $contact, true),
            'telegram' => in_array('telegram', $contact, true),
            'max' => in_array('max', $contact, true),
            'extras' => $extras ?: null,
            'promo_code' => $promo,
        ]);
        if ($quote && ($quote['ok'] ?? false)) {
            $booking->fill(['quote_snapshot' => $quote, 'total' => $quote['total'], 'discount' => $quote['discount'] ?? 0]);
        }
        $booking->save();

        $telegram->bookingDetailsUpdated($booking, in_array('call', $contact, true));

        return redirect()->to(URL::signedRoute('booking.thanks', $booking).'#details')
            ->with('details_saved', true)
            ->with('promo_result', $quote['promo'] ?? null);
    }

    /** Статус заявки по короткой ссылке из SMS. */
    public function status(string $token): View
    {
        $booking = Booking::query()->where('public_token', $token)->firstOrFail();

        return $this->thanks($booking, app(PrepaymentService::class));
    }

    /** Событие для календаря телефона: выдача машины. */
    public function calendar(Booking $booking): Response
    {
        $booking->loadMissing(['car', 'pickupLocation']);
        $esc = fn (?string $s) => str_replace(['\\', ',', ';', "\n"], ['\\\\', '\\,', '\\;', '\\n'], (string) $s);
        $start = $booking->starts_at?->copy()->utc();
        $lines = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Car on Time//RU', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:booking-'.$booking->id.'@'.parse_url((string) config('app.url'), PHP_URL_HOST),
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$start?->format('Ymd\THis\Z'),
            'DTEND:'.$start?->copy()->addHour()->format('Ymd\THis\Z'),
            'SUMMARY:'.$esc('Получение машины: '.($booking->car?->name ?? 'аренда авто')),
            'LOCATION:'.$esc($booking->pickupLocation?->name),
            'DESCRIPTION:'.$esc('Заявка №'.$booking->id.'. Возьмите паспорт и водительское удостоверение. Телефон: '.Setting::get('phone')),
            'BEGIN:VALARM', 'TRIGGER:-PT2H', 'ACTION:DISPLAY', 'DESCRIPTION:'.$esc('Через 2 часа — получение машины'), 'END:VALARM',
            'END:VEVENT', 'END:VCALENDAR',
        ];

        return response(implode("\r\n", $lines)."\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="car-on-time-'.$booking->id.'.ics"',
        ]);
    }

    /**
     * Выбранные доп. услуги в виде, который понимает QuoteCalculator.
     *
     * @param  list<int|string>  $ids
     * @return list<array{id: int, name: string, price_per_day: int, waives_deposit: bool}>
     */
    public static function extras(array $ids): array
    {
        if (! $ids) {
            return [];
        }

        return Extra::query()->active()->whereIn('id', $ids)->get()
            ->map(fn (Extra $extra) => [
                'id' => $extra->id,
                'name' => $extra->name,
                'price_per_day' => $extra->is_free ? 0 : (int) $extra->price_per_day,
                'waives_deposit' => $extra->waives_deposit,
            ])
            ->all();
    }
}
