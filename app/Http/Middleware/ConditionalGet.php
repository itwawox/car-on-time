<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Last-Modified / ETag и ответ 304 для публичных страниц.
 * Яндекс Вебмастер рекомендует: робот не перекачивает неизменённые страницы и быстрее обходит новые.
 */
class ConditionalGet
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethodCacheable() || $response->getStatusCode() !== 200 || $request->is('admin*', 'livewire*')) {
            return $response;
        }

        if (! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }

        if (! $response->headers->has('ETag') && is_string($content = $response->getContent()) && $content !== '') {
            $withoutToken = (string) preg_replace('/name="_token" value="[^"]*"|<meta name="csrf-token"[^>]*>/', '', $content);
            $response->setEtag(md5($withoutToken.$this->formToken($request)), true);
        }

        $response->isNotModified($request);

        return $response;
    }

    /**
     * Токен форм посетителя входит в ETag: когда сессия истекла, браузер получает свежую страницу,
     * а не 304 с кешированной формой со старым токеном (отправка такой формы — ошибка 419).
     * Поисковым роботам токен не нужен — они не отправляют формы и получают 304 по содержимому.
     */
    private function formToken(Request $request): string
    {
        if (! $request->hasSession() || preg_match('/bot|crawl|spider|slurp/i', (string) $request->userAgent())) {
            return '';
        }

        return (string) $request->session()->token();
    }
}
