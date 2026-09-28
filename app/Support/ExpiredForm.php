<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * «Страница устарела» (ошибка 419): форма открыта дольше жизни сессии.
 * Вместо голой страницы ошибки посетитель возвращается к форме с введёнными данными,
 * а скрипт получает свежий токен и может отправить форму ещё раз сам.
 */
class ExpiredForm
{
    public const DEFAULT_TEXT = 'Страница устарела, пока была открыта. Данные сохранили — проверьте их и отправьте ещё раз.';

    public static function text(): string
    {
        return Setting::get('form_expired_text') ?: self::DEFAULT_TEXT;
    }

    /** Ответ на 419 для витрины; null — админка и Livewire обрабатывают его сами. */
    public static function render(HttpException $e, Request $request): JsonResponse|RedirectResponse|null
    {
        if ($e->getStatusCode() !== 419 || $request->is('admin*', 'livewire*')) {
            return null;
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => self::text(), 'token' => $request->session()->token()], 419);
        }

        return back()
            ->withInput($request->except('_token'))
            ->withErrors(['form' => self::text()]);
    }
}
