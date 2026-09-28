@php
    use App\Models\Setting;

    $brand = Setting::get('brand_name', 'Car on Time');
    $phone = Setting::get('phone', '+7 978 948 48 48');
    $phoneRaw = Setting::get('phone_raw', '+79789484848');
    $hours = Setting::get('hours', 'Заявки принимаем круглосуточно');
    $email = Setting::get('email', 'info@car-on-time.ru');
    $whatsapp = Setting::get('whatsapp', 'https://wa.me/79789484848');
    $telegram = Setting::get('telegram');

    $seoGeneral = \App\Support\Seo\SeoSettings::general();
    $pageTitle = filled($title ?? null) ? $title : \App\Support\Seo\SeoSettings::meta('home', 'title');
    $pageDescription = filled($description ?? null) ? $description : $seoGeneral['default_description'];
    $pageCanonical = $canonical ?? url()->current();
    $pageImage = $ogImage ?? (filled($seoGeneral['og_image']) ? url(\Illuminate\Support\Facades\Storage::disk('public')->url($seoGeneral['og_image'])) : url('/og-image.jpg')); // фирменная обложка для превью в мессенджерах

    $hasArticles = \Illuminate\Support\Facades\Cache::remember('articles:has-published', 600, fn () => \App\Models\Article::query()->published()->exists());
    $hasPromotions = \Illuminate\Support\Facades\Cache::remember('promotions:has-active', 600, fn () => \App\Models\Promotion::query()->active()->exists());
    $nav = [
        ['label' => 'Автомобили', 'url' => route('catalog'), 'active' => request()->routeIs('catalog*', 'klass*', 'kuzov*', 'korobka*', 'marka*', 'car.show', 'city*')],
        ['label' => 'Подбор', 'url' => route('quiz'), 'active' => request()->routeIs('quiz*')],
        $hasPromotions ? ['label' => 'Акции', 'url' => route('promotions'), 'active' => request()->routeIs('promotion*')] : null,
        ['label' => 'Условия', 'url' => route('page', 'usloviya'), 'active' => request()->is('usloviya')],
        ['label' => 'Контакты', 'url' => route('page', 'kontakty'), 'active' => request()->is('kontakty')],
    ];
    $nav = array_values(array_filter($nav));
@endphp
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <script>document.documentElement.classList.add('js');try{var t=localStorage.getItem('theme');if(t==='dark'||t==='light')document.documentElement.dataset.theme=t;if(localStorage.getItem('catalog:view')==='table')document.documentElement.dataset.catalogView='table'}catch(e){}</script>
    <meta name="color-scheme" content="light dark">
    <title>{{ $pageTitle }}</title>
    @if($pageDescription)
        <meta name="description" content="{{ $pageDescription }}">
    @endif
    @if(!empty($noindex))
        <meta name="robots" content="noindex,follow">
    @else
        <meta name="robots" content="index,follow,max-image-preview:large">
    @endif
    <link rel="canonical" href="{{ $pageCanonical }}">
    <meta name="theme-color" content="#0f3344">
    <meta name="format-detection" content="telephone=no">

    <meta property="og:type" content="{{ $ogType ?? 'website' }}">
    <meta property="og:locale" content="ru_RU">
    <meta property="og:site_name" content="{{ $brand }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    @if($pageDescription)
        <meta property="og:description" content="{{ $pageDescription }}">
    @endif
    <meta property="og:url" content="{{ $pageCanonical }}">
    @if($pageImage)
        <meta property="og:image" content="{{ $pageImage }}">
    @endif
    <meta name="twitter:card" content="{{ $pageImage ? 'summary_large_image' : 'summary' }}">

    <link rel="icon" href="/favicon.ico" sizes="48x48">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <link rel="preload" href="{{ \Illuminate\Support\Facades\Vite::asset('node_modules/@fontsource-variable/manrope/files/manrope-cyrillic-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.jsonld', ['jsonld' => array_filter(array_merge(
        [app(\App\Services\Seo::class)->localBusiness()],
        $jsonld ?? []
    ))])
    @if($yv = Setting::get('yandex_verification'))
        <meta name="yandex-verification" content="{{ $yv }}">
    @endif
    @if($gv = Setting::get('google_verification'))
        <meta name="google-site-verification" content="{{ $gv }}">
    @endif
    @stack('head')
    @if(app()->isProduction() && ($metrika = (int) Setting::get('yandex_metrika_id')))
        {{-- Метрика (аналитические cookie) — ТОЛЬКО после согласия «Принять все» в баннере cookie (152-ФЗ).
             После согласия грузится при простое или первом действии — не тормозит PageSpeed --}}
        <script>
            window.dataLayer = window.dataLayer || [];
            (function () {
                var loaded = false;
                function consented() { try { return localStorage.getItem('cookie:consent') === 'all'; } catch (e) { return false; } }
                function load() {
                    if (loaded || !consented()) return; loaded = true;
                    (function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};m[i].l=1*new Date();
                    k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
                    (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");
                    ym({{ $metrika }}, "init", { clickmap: true, trackLinks: true, accurateTrackBounce: true, webvisor: true, ecommerce: "dataLayer" });
                    window.ymCounterId = {{ $metrika }};
                    // Цели, случившиеся до загрузки счётчика (уже после согласия)
                    (window.ymPendingGoals || []).splice(0).forEach(function (g) { ym({{ $metrika }}, 'reachGoal', g[0], g[1]); });
                }
                ['scroll', 'pointerdown', 'keydown', 'touchstart'].forEach(function (e) { addEventListener(e, load, { once: true, passive: true }); });
                addEventListener('load', function () { ('requestIdleCallback' in window) ? requestIdleCallback(load, { timeout: 3000 }) : setTimeout(load, 2000); });
                addEventListener('cookie-consent', load);
            })();
        </script>
    @endif
</head>
<body class="@yield('body_class')">
<a class="skip-link" href="#content">К содержимому</a>
{{-- Фиксированные панели — в начале документа: браузер раскладывает их в первом кадре, без сдвига вёрстки --}}
@include('partials.contact-fab')
@stack('sticky')

<header class="site-header">
    <div class="container-x header-row">
        <a class="brand" href="{{ route('home') }}" aria-label="{{ $brand }} — на главную">
            @include('partials.logo', ['variant' => 'header'])
        </a>

        <nav class="main-nav" aria-label="Основное меню">
            @foreach($nav as $item)
                <a href="{{ $item['url'] }}" @if($item['active']) aria-current="page" @endif>{{ $item['label'] }}</a>
            @endforeach
        </nav>

        <div class="header-actions">
            <button class="search-trigger" type="button" data-search-open commandfor="search-dialog" command="show-modal" aria-haspopup="dialog" aria-label="Поиск по каталогу">
                @include('partials.icon', ['name' => 'search', 'size' => 18])
                <span class="search-trigger-text">{{ \App\Support\Search\SearchSettings::text('header_trigger') }}</span>
                <kbd class="search-trigger-kbd" data-shortcut-label>Ctrl K</kbd>
            </button>
            <a class="icon-btn fav-link" href="{{ route('favorites') }}" data-fav-link aria-label="Избранное" data-tooltip="Избранное" rel="nofollow">@include('partials.icon', ['name' => 'heart', 'size' => 18])<span class="fav-count" data-fav-count hidden></span></a>
            <button class="icon-btn theme-toggle" type="button" data-theme-toggle aria-label="Тёмная тема" title="Тёмная тема">
                <span class="theme-icon-moon">@include('partials.icon', ['name' => 'moon', 'size' => 18])</span>
                <span class="theme-icon-sun">@include('partials.icon', ['name' => 'sun', 'size' => 18])</span>
            </button>
            <div class="header-phone">
                <a href="tel:{{ $phoneRaw }}">{{ $phone }}</a>
                <span>{{ $hours }}</span>
            </div>
            <a class="btn btn-accent btn-sm header-cta" href="{{ route('quiz') }}">Подобрать авто</a>
            <button class="icon-btn menu-toggle" type="button" data-menu-open commandfor="mobile-menu" command="show-modal" aria-controls="mobile-menu" aria-expanded="false" aria-label="Открыть меню">
                @include('partials.icon', ['name' => 'menu', 'size' => 22])
            </button>
        </div>
    </div>
</header>

<dialog class="mobile-menu" id="mobile-menu" aria-label="Меню">
    <div class="mobile-menu-head">
        <a class="brand" href="{{ route('home') }}" aria-label="{{ $brand }} — на главную">
            @include('partials.logo', ['variant' => 'menu'])
        </a>
        <button class="icon-btn" type="button" data-menu-close commandfor="mobile-menu" command="close" aria-label="Закрыть меню">
            @include('partials.icon', ['name' => 'close', 'size' => 22])
        </button>
    </div>
    <nav aria-label="Мобильное меню">
        @foreach($nav as $item)
            <a href="{{ $item['url'] }}" @if($item['active']) aria-current="page" @endif>
                {{ $item['label'] }}
                @include('partials.icon', ['name' => 'chevron-right', 'size' => 18])
            </a>
        @endforeach
        <a href="{{ route('faq') }}" @if(request()->routeIs('faq')) aria-current="page" @endif>
            Вопросы и ответы
            @include('partials.icon', ['name' => 'chevron-right', 'size' => 18])
        </a>
        <a href="{{ route('cabinet') }}" rel="nofollow" @if(request()->routeIs('cabinet')) aria-current="page" @endif>
            Мои брони
            @include('partials.icon', ['name' => 'chevron-right', 'size' => 18])
        </a>
        <a href="{{ route('favorites') }}" data-fav-link rel="nofollow">
            <span>Избранное <span class="fav-count-inline" data-fav-count hidden></span></span>
            @include('partials.icon', ['name' => 'heart', 'size' => 18])
        </a>
    </nav>
    <div class="mobile-menu-foot">
        <p class="note" style="margin:0 0 4px">{{ $hours }}</p>
        <a class="btn btn-primary btn-lg btn-block" href="tel:{{ $phoneRaw }}">
            @include('partials.icon', ['name' => 'phone', 'size' => 18]) {{ $phone }}
        </a>
        @include('partials.messengers', ['variant' => 'buttons'])
    </div>
</dialog>

<script type="application/json" id="search-config">@json(\App\Support\Search\SearchSettings::ui() + ['min_query_length' => \App\Support\Search\SearchSettings::limits()['min_query_length']])</script>
<dialog class="search-dialog" id="search-dialog" aria-label="Поиск по каталогу">
    @include('partials.search-box', ['id' => 'palette', 'variant' => 'palette', 'label' => 'Поиск по каталогу'])
</dialog>

<main id="content">
    {{-- Форма была открыта слишком долго (419): в карточке машины сообщение стоит у самой формы --}}
    @if($errors->has('form') && ! View::hasSection('inline_form_errors'))
        <div class="container-x"><p class="form-expired" role="alert">{{ $errors->first('form') }}</p></div>
    @endif
    @yield('content')
</main>

<footer class="site-footer">
    <div class="container-x">
        <div class="footer-grid">
            <div>
                <a class="brand" href="{{ route('home') }}" aria-label="{{ $brand }} — на главную">
                    @include('partials.logo', ['variant' => 'footer'])
                </a>
                <p style="margin:16px 0 0;max-width:40ch;font-size:.9375rem">{{ str_replace(':year', (string) Setting::get('founding_year', ''), Setting::get('footer_about', 'Аренда авто в Крыму с :year года. Подбираем машину под ваши даты и подтверждаем наличие за 15 минут.')) }}</p>
                @if($disclaimer = Setting::get('disclaimer'))
                    <p style="margin:12px 0 0;font-size:.8125rem;color:#8fb1c2;max-width:48ch">{{ $disclaimer }}</p>
                @endif
            </div>
            <nav aria-label="Каталог">
                <p class="footer-title">Автомобили</p>
                <ul class="footer-list">
                    <li><a href="{{ route('catalog') }}">Весь каталог</a></li>
                    <li><a href="{{ route('korobka', 'avtomat') }}">На автомате</a></li>
                    <li><a href="{{ route('quiz') }}">Подбор за 30 секунд</a></li>
                    @foreach(\App\Models\City::query()->published()->where('show_in_footer', true)->get(['slug', 'name']) as $footerCity)
                        <li><a href="{{ route('city', $footerCity->slug) }}">{{ $footerCity->name }}</a></li>
                    @endforeach
                </ul>
            </nav>
            <nav aria-label="Информация">
                <p class="footer-title">Клиентам</p>
                <ul class="footer-list">
                    <li><a href="{{ route('page', 'usloviya') }}">Условия аренды</a></li>
                    @if($hasArticles)<li><a href="{{ route('articles') }}">Статьи</a></li>@endif
                    @if($hasPromotions)<li><a href="{{ route('promotions') }}">Акции</a></li>@endif
                    <li><a href="{{ route('page', 'yurlicam') }}">Юрлицам</a></li>
                    <li><a href="{{ route('page', 'sdat-avto') }}">Сдать авто</a></li>
                    <li><a href="{{ route('cabinet') }}" rel="nofollow">Мои брони</a></li>
                    <li><a href="{{ route('faq') }}">Вопросы и ответы</a></li>
                    <li><a href="{{ route('reviews') }}">Отзывы</a></li>
                    <li><a href="{{ route('page', 'kontakty') }}">Контакты</a></li>
                </ul>
            </nav>
            <div>
                <p class="footer-title">Связаться</p>
                <ul class="footer-list">
                    <li><a href="tel:{{ $phoneRaw }}" style="font-size:1.125rem;font-weight:700">{{ $phone }}</a></li>
                    <li style="color:#8fb1c2">{{ $hours }}</li>
                    <li><a href="mailto:{{ $email }}">{{ $email }}</a></li>
                    @if($pickup = Setting::get('pickup_point'))<li>{{ $pickup }}</li>@endif
                    @if($address = Setting::get('address'))<li style="color:#8fb1c2;font-size:.875rem">{{ $address }}</li>@endif
                    <li class="flex flex-wrap items-center gap-2" style="margin-top:4px">
                        @include('partials.messengers', ['variant' => 'icons', 'withVk' => true])
                    </li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <span>
                © {{ Setting::get('founding_year') ? Setting::get('founding_year').'–' : '' }}{{ date('Y') }} {{ $brand }}
                @if($legal = Setting::get('legal_name'))· {{ $legal }}@endif
                @if($inn = Setting::get('inn'))· ИНН {{ $inn }}@endif
                @if($ogrn = Setting::get('ogrn'))· ОГРН {{ $ogrn }}@endif
            </span>
            <span class="footer-legal">
                <a href="{{ route('page', 'politika-konfidencialnosti') }}">Политика обработки персональных данных</a>
                <a href="{{ route('page', 'soglasie-pdn') }}">Согласие на обработку данных</a>
                <a href="{{ route('page', 'cookie') }}">Политика cookie</a>
                <a href="{{ route('page', 'rekomendatelnye-tehnologii') }}">Рекомендательные технологии</a>
                <a href="{{ route('page', 'polzovatelskoe-soglashenie') }}">Пользовательское соглашение</a>
                <a href="#" data-cookie-settings>Настройки cookie</a>
            </span>
        </div>
    </div>
</footer>
@include('partials.cookie-banner')

{{-- Мгновенные переходы: браузер заранее загружает страницу, на ссылку которой навели или нажали --}}
<script type="speculationrules">
{
    "prefetch": [{
        "where": { "and": [
            { "href_matches": "/*" },
            { "not": { "href_matches": ["/admin*", "/livewire*", "/zayavka*", "/quote*", "/poisk/podskazki*", "/*.ics", "/*\\?*sort=*"] } },
            { "not": { "selector_matches": "[rel~=nofollow], [target=_blank], [download]" } }
        ] },
        "eagerness": "moderate"
    }],
    "prerender": [{
        "where": { "href_matches": "/avto/*" },
        "eagerness": "moderate"
    }]
}
</script>
{!! \App\Support\Icons::sprite() !!}
</body>
</html>
