<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 301-редиректы из админки («Сайт → Редиректы»).
 * Сохраняют позиции старых страниц car-on-time.ru (booking.php?carid=…, contact.php и т. п.).
 */
class HandleRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        // Один адрес — одна страница: «/katalog/» и «/katalog» не должны быть дублями
        $path = $request->getPathInfo();
        if ($path !== '/' && str_ends_with($path, '/') && ! str_starts_with($path, '/admin') && ! str_starts_with($path, '/livewire')) {
            $query = $request->getQueryString();

            return redirect(rtrim($path, '/').($query ? '?'.$query : ''), 301);
        }

        $map = Redirect::map();
        if ($map === []) {
            return $next($request);
        }

        foreach (Redirect::candidates($request->getPathInfo(), $request->query()) as $key) {
            if (isset($map[$key])) {
                [$to, $status] = $map[$key];

                return redirect($to, $status)->header('Cache-Control', 'public, max-age=86400');
            }
        }

        return $next($request);
    }
}
