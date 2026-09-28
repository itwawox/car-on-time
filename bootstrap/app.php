<?php

use App\Http\Middleware\ConditionalGet;
use App\Http\Middleware\HandleRedirects;
use App\Http\Middleware\SecurityHeaders;
use App\Support\ExpiredForm;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Старые адреса сайта ведут на новые до того, как роутер ответит 404
        $middleware->prepend(HandleRedirects::class);
        $middleware->web(append: [ConditionalGet::class, SecurityHeaders::class]);
        // Вебхуки внешних систем приходят без CSRF-токена, их подлинность проверяет контроллер
        $middleware->validateCsrfTokens(except: ['integrations/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // Форма открыта слишком долго (419): назад к форме с данными, а не голая страница ошибки
        $exceptions->render(fn (HttpException $e, Request $request) => ExpiredForm::render($e, $request));
    })->create();
