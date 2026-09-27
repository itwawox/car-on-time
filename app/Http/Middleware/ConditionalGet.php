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
            // CSRF-токен в форме уникален для посетителя — в хеш его не включаем
            $response->setEtag(md5((string) preg_replace('/name="_token" value="[^"]*"|<meta name="csrf-token"[^>]*>/', '', $content)), true);
        }

        $response->isNotModified($request);

        return $response;
    }
}
