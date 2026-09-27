<?php

// Заголовки безопасности ставит само приложение: на обычном хостинге (Apache) конфиг nginx не работает

it('sends security headers on public pages', function () {
    $this->get('/')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeaderMissing('X-Frame-Options')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
        ->assertHeaderMissing('Strict-Transport-Security');
});

it('allows embedding only for own site and Metrika webvisor, never for admin', function () {
    expect($this->get('/')->headers->get('Content-Security-Policy'))
        ->toContain("frame-ancestors 'self'")->toContain('https://metrika.yandex.ru');

    $this->get('/admin/login')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'");
});

it('turns on HSTS only in production over https', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get('https://car-on-time.test/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    $this->get('http://car-on-time.test/')->assertHeaderMissing('Strict-Transport-Security');
});
