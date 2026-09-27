<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\Payments\PrepaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Онлайн-предоплата по заявке через ЮKassa. */
class PaymentController extends Controller
{
    /** Кнопка «Внести предоплату» на странице заявки → платёжная страница ЮKassa. */
    public function pay(string $token, PrepaymentService $payments): RedirectResponse
    {
        $booking = Booking::query()->where('public_token', $token)->firstOrFail();

        try {
            $payment = $payments->start($booking);
        } catch (RuntimeException $e) {
            report($e);

            return redirect()->to($booking->statusUrl())->with('payment_error', 'Не получилось открыть оплату. Попробуйте ещё раз или позвоните нам — оплатить можно и при получении.');
        }

        return $payment->confirmation_url
            ? redirect()->away($payment->confirmation_url)
            : redirect()->to($booking->statusUrl());
    }

    /**
     * Уведомление ЮKassa о смене статуса. Телу запроса не доверяем:
     * берём только id платежа и спрашиваем статус у ЮKassa сами.
     */
    public function webhook(Request $request, PrepaymentService $payments): JsonResponse
    {
        $id = (string) $request->input('object.id');
        $payment = $id !== '' ? Payment::query()->where('provider_id', $id)->first() : null;

        if ($payment) {
            rescue(fn () => $payments->refresh($payment));
        }

        return response()->json(['ok' => true]);
    }
}
