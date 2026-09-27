{{--
    Логотип: знак (кольцо часов с галочкой — «вовремя») + «Car On Time» + мелкий перевод на русском.
    Тексты — «Настройки сайта → Логотип». $variant: header | footer.
--}}
@php
    use App\Models\Setting;
    $word1 = Setting::get('logo_word_1') ?: 'Car On';
    $word2 = Setting::get('logo_word_2') ?: 'Time';
    $tagline = Setting::get('logo_tagline') ?: 'машина вовремя';
    $uid = 'lg'.($variant ?? 'header');
@endphp
<span class="logo logo-{{ $variant ?? 'header' }}">
    <svg class="logo-mark" width="40" height="40" viewBox="0 0 40 40" aria-hidden="true" focusable="false">
        <defs>
            <linearGradient id="{{ $uid }}-bg" x1="0" y1="0" x2="40" y2="40" gradientUnits="userSpaceOnUse">
                <stop offset="0" stop-color="#2b7fa3"/>
                <stop offset="1" stop-color="#174a60"/>
            </linearGradient>
        </defs>
        <rect width="40" height="40" rx="11" fill="url(#{{ $uid }}-bg)"/>
        <rect x="17.6" y="6" width="4.8" height="3.4" rx="1.4" fill="#e9a15b"/>
        <circle cx="20" cy="22" r="10.2" fill="none" stroke="#fff" stroke-width="2.6"/>
        <path d="m15.4 22.3 3.2 3.2 6.2-6.6" fill="none" stroke="#fff" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    <span class="logo-text">
        <span class="logo-name">{{ $word1 }} <b>{{ $word2 }}</b></span>
        <span class="logo-tagline">{{ $tagline }}</span>
    </span>
</span>
