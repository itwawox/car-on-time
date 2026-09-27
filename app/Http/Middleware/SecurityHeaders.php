<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Заголовки безопасности на каждом ответе сайта.
 * Ставятся приложением, а не веб-сервером: на обычном хостинге (Apache) конфиг nginx из deploy/ не работает.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        // Встраивать сайт в чужие страницы нельзя (защита от «кликджекинга»). Исключение — Вебвизор Метрики:
        // он показывает записи визитов, встраивая сайт в metrika.yandex.ru. Админку не встраивает никто.
        if ($request->is('admin', 'admin/*')) {
            $response->headers->set('X-Frame-Options', 'DENY');
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'none'");
        } else {
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'self' https://metrika.yandex.ru https://metrika.yandex.by https://metrica.yandex.com https://*.webvisor.com");
        }
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // HTTPS строго: только на боевом сайте и только по HTTPS, иначе локальный сайт на http перестанет открываться
        if (app()->isProduction() && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Не сообщаем версию PHP
        $response->headers->remove('X-Powered-By');
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $response;
    }
}
