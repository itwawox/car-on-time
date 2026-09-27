{{-- Баннер cookie: аналитика (Метрика) — только после «Принять все». Выбор хранится в браузере, изменить — «Настройки cookie» в подвале --}}
@php($S = \App\Models\Setting::class)
<div class="cookie-banner" data-cookie-banner role="region" aria-label="Использование cookie" hidden>
    <p>{{ $S::get('cookie_text') ?: 'Используем cookie. Аналитику (Яндекс.Метрика) включим только с вашего согласия.' }}
        <a href="{{ route('page', 'cookie') }}">Подробнее</a></p>
    <div class="cookie-actions">
        <button type="button" class="btn btn-outline btn-sm" data-cookie-choice="necessary">Только нужные</button>
        <button type="button" class="btn btn-primary btn-sm" data-cookie-choice="all">Принять</button>
    </div>
</div>
