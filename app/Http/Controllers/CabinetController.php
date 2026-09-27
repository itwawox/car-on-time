<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\IntegrationLog;
use App\Models\Setting;
use App\Services\QuoteCalculator;
use App\Services\Sms\SmsException;
use App\Services\Sms\SmsSender;
use App\Services\Sms\SmsSettings;
use App\Services\TelegramNotifier;
use App\Support\Availability;
use App\Support\CustomerSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Личный кабинет клиента: все его брони по номеру телефона, продление, «взять снова».
 * Вход без пароля — код из SMS или кнопка на странице заявки (ссылка на неё уже у клиента).
 */
class CabinetController extends Controller
{
    private const CODE_TTL_MINUTES = 10;

    private const MAX_ATTEMPTS = 5;

    public function index(): View
    {
        if (! CustomerSession::phone()) {
            return view('cabinet', ['stage' => session('code_sent_to') ? 'code' : 'phone', 'smsLogin' => SmsSettings::enabled()]);
        }

        $bookings = CustomerSession::bookings()->with(['car', 'pickupLocation'])->latest('starts_at')->get();
        [$upcoming, $past] = $bookings->partition(fn (Booking $b) => $b->ends_at?->isFuture() && $b->status !== 'declined');

        return view('cabinet', [
            'stage' => 'list',
            'upcoming' => $upcoming->sortBy('starts_at')->values(),
            'past' => $past->values(),
        ]);
    }

    /** Шаг 1: номер телефона → SMS с кодом. Есть ли у номера брони, не сообщаем. */
    public function sendCode(Request $request, SmsSender $sms): RedirectResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'min:10', 'max:32']], ['phone.required' => 'Укажите телефон, на который оформляли заявку.']);
        $digits = CustomerSession::digits($data['phone']);

        if (! SmsSettings::enabled()) {
            return back()->withErrors(['phone' => 'Вход по SMS сейчас недоступен. Откройте ссылку из сообщения о заявке и нажмите «Все мои брони» — или позвоните нам.']);
        }

        $limiter = 'cabinet-code:'.$digits;
        if (RateLimiter::tooManyAttempts($limiter, 3)) {
            return back()->withErrors(['phone' => 'Слишком много запросов. Повторите через '.ceil(RateLimiter::availableIn($limiter) / 60).' мин.'])->withInput();
        }
        RateLimiter::hit($limiter, 15 * 60);

        $code = (string) random_int(100000, 999999);
        Cache::put('cabinet-code:'.$digits, ['hash' => Hash::make($code), 'attempts' => 0], now()->addMinutes(self::CODE_TTL_MINUTES));

        $text = strtr((string) (Setting::get('sms_tpl_login_code') ?: '{brand}: код для входа в личный кабинет {code}. Никому его не сообщайте.'), [
            '{brand}' => (string) Setting::get('brand_name', 'Car on Time'),
            '{code}' => $code,
        ]);
        $log = new IntegrationLog(['integration' => 'sms', 'event' => 'sms.login_code', 'request' => ['to' => $digits, 'text' => str_replace($code, '******', $text)]]);
        try {
            $log->fill(['status' => 'success', 'response' => $sms->send($digits, $text)])->save();
        } catch (SmsException $e) {
            $log->fill(['status' => 'failed', 'error' => $e->getMessage()])->save();

            return back()->withErrors(['phone' => 'Не получилось отправить SMS. Попробуйте позже или позвоните нам.'])->withInput();
        }

        return redirect()->route('cabinet')->with('code_sent_to', $digits);
    }

    /** Шаг 2: код из SMS → вход. */
    public function verifyCode(Request $request): RedirectResponse
    {
        $data = $request->validate(['phone' => ['required', 'string'], 'code' => ['required', 'digits:6']], ['code.digits' => 'Код — 6 цифр из SMS.']);
        $digits = CustomerSession::digits($data['phone']);
        $key = 'cabinet-code:'.$digits;
        $entry = Cache::get($key);

        if (! $entry || $entry['attempts'] >= self::MAX_ATTEMPTS) {
            Cache::forget($key);

            return redirect()->route('cabinet')->withErrors(['phone' => 'Код устарел. Запросите новый.']);
        }

        if (! Hash::check($data['code'], $entry['hash'])) {
            Cache::put($key, [...$entry, 'attempts' => $entry['attempts'] + 1], now()->addMinutes(self::CODE_TTL_MINUTES));

            return redirect()->route('cabinet')->with('code_sent_to', $digits)->withErrors(['code' => 'Неверный код.']);
        }

        Cache::forget($key);
        CustomerSession::login($digits);

        return redirect()->route('cabinet');
    }

    /** Кнопка «Все мои брони» на странице заявки: ссылка на неё — доказательство, что номер ваш. */
    public function fromBooking(string $token): RedirectResponse
    {
        $booking = Booking::query()->where('public_token', $token)->firstOrFail();
        CustomerSession::login($booking->phone);

        return redirect()->route('cabinet');
    }

    public function logout(): RedirectResponse
    {
        CustomerSession::logout();

        return redirect()->route('cabinet');
    }

    /**
     * Запрос на продление: считаем доплату и проверяем, свободна ли машина. Подтверждает менеджер.
     */
    public function extend(Request $request, Booking $booking, QuoteCalculator $calculator, TelegramNotifier $telegram): RedirectResponse
    {
        abort_unless(CustomerSession::owns($booking), 404);
        abort_unless($booking->status === 'confirmed' && $booking->ends_at?->isFuture(), 422, 'Эту бронь продлить нельзя.');

        $data = $request->validate(['ends_at' => ['required', 'date', 'after:'.$booking->ends_at->format('Y-m-d H:i')]], ['ends_at.after' => 'Новая дата возврата должна быть позже текущей.']);
        $newEnd = Carbon::parse($data['ends_at']);
        $booking->loadMissing('car', 'pickupLocation', 'returnLocation');

        $conflicts = $booking->car ? Availability::conflicts($booking->car, $booking->ends_at, $newEnd, $booking->id) : collect();
        if ($conflicts->isNotEmpty()) {
            $busyFrom = $conflicts->first()->starts_at->translatedFormat('j F, H:i');

            return back()->withErrors(['ends_at_'.$booking->id => 'Машина занята с '.$busyFrom.'. Выберите дату раньше или позвоните — подберём вариант.']);
        }

        $quote = $calculator->quote($booking->car, $booking->starts_at, $newEnd, $booking->pickupLocation, $booking->returnLocation, (array) $booking->extras, $booking->promo_code);
        $extra = max(0, (int) ($quote['total'] ?? 0) - (int) $booking->total);
        $money = number_format($extra, 0, ',', ' ').' ₽';

        $booking->events()->create(['type' => 'note', 'comment' => 'Клиент просит продлить до '.$newEnd->format('d.m H:i').', доплата по расчёту ≈ '.$money]);
        $telegram->send(implode("\n", [
            '🔁 Продление, заявка №'.$booking->id,
            'Авто: '.($booking->car?->name ?? '—'),
            'Сейчас до: '.$booking->ends_at->format('d.m H:i').' → просит до: '.$newEnd->format('d.m H:i'),
            'Доплата по расчёту: '.$money,
            'Телефон: '.$booking->phone,
        ]), ['booking' => $booking->id]);

        return back()->with('extended_'.$booking->id, 'Запрос отправлен: продление до '.$newEnd->translatedFormat('j F, H:i').', доплата ≈ '.$money.'. Менеджер подтвердит по телефону.');
    }
}
