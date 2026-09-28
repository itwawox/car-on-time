@php
    use App\Models\Setting;


    $cities = \App\Models\City::query()->published()->where('show_on_home', true)->get();

    $phoneRaw = Setting::get('phone_raw', '+79789484848');
    $phone = Setting::get('phone', '+7 978 948 48 48');
@endphp
@extends('layouts.app', ['jsonld' => $jsonld ?? [], 'title' => $title ?? null, 'description' => $description ?? null])

@section('content')
<section @class(['hero', 'has-stage' => ! empty($stage)])>
    <div class="container-x">
        @include('partials.hero-stage')
        <div class="hero-anim">
            <span class="eyebrow">@include('partials.icon', ['name' => 'pin', 'size' => 14, 'stroke' => 2]) {{ Setting::get('hero_eyebrow', 'Крым · доставка по полуострову') }}</span>
            <h1>{{ \App\Support\Typography::nbsp(Setting::get('hero_title', 'Аренда авто в Крыму без предоплаты')) }}</h1>
            <p class="hero-lead">{{ str_replace(':count', $count, Setting::get('hero_lead', 'Car on Time — агент проката. :count автомобилей, доставка по полуострову, подтверждение наличия за 15 минут. Заявки принимаем круглосуточно.')) }}</p>

            @include('partials.hero-booking')
            <form class="card search-card" id="panel-name" role="tabpanel" aria-labelledby="tab-name" data-hero-panel-inactive method="get" action="{{ route('search') }}" aria-label="Поиск автомобиля">
                <div class="search-card-query">
                    <label class="field-label" for="hero-input">{{ \App\Support\Search\SearchSettings::text('hero_label') }}</label>
                    @include('partials.search-box', ['id' => 'hero', 'variant' => 'hero', 'asForm' => false, 'placeholder' => \App\Support\Search\SearchSettings::text('placeholder_hero')])
                </div>
                <div>
                    <label class="field-label" for="s-class">Класс</label>
                    <select class="field" id="s-class" name="class">
                        <option value="">Все классы</option>
                        @foreach($classes as $class)
                            <option value="{{ $class->slug }}">{{ $class->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label" for="s-kp">Коробка</label>
                    <select class="field" id="s-kp" name="kp">
                        <option value="">Любая</option>
                        <option value="at">Автомат</option>
                        <option value="mt">Механика</option>
                    </select>
                </div>
                <button class="btn btn-accent btn-lg" type="submit">{{ \App\Support\Search\SearchSettings::text('hero_submit') }}</button>
            </form>

            @include('partials.hero-hints')
            @include('partials.trust-strip', ['count' => $count])
        </div>
    </div>
</section>



<div class="container-x home-memory">@include('partials.memory-blocks', ['similar' => true, 'resume' => true, 'welcome' => true, 'id' => 'home'])</div>

@include('partials.trip-scenarios')

@include('partials.popular-tabs')

@php($homePromos = \App\Models\Promotion::query()->active()->limit(3)->get())
@if($homePromos->isNotEmpty())
<section class="section section-tight" aria-labelledby="h-promos">
    <div class="container-x">
        <div class="section-head" data-reveal>
            <h2 id="h-promos">{{ Setting::get('promotions_title') ?: 'Акции' }}</h2>
            <a class="link-arrow" href="{{ route('promotions') }}">Все акции @include('partials.icon', ['name' => 'arrow-right', 'size' => 18])</a>
        </div>
        <div class="promo-grid">
            @foreach($homePromos as $promotion)
                @include('partials.promo-card', ['promotion' => $promotion])
            @endforeach
        </div>
    </div>
</section>
@endif

@include('partials.trust-steps')
<div class="container-x home-included">@include('partials.included')</div>

<section class="section section-tight" aria-labelledby="h-cities">
    <div class="container-x">
        <div class="section-head" data-reveal>
            <div>
                <h2 id="h-cities">Города и точки выдачи</h2>
                <p>{{ Setting::get('home_cities_lead', 'Передадим машину в офисе, в аэропорту или привезём по адресу.') }}</p>
            </div>
        </div>
        <div class="tile-grid" data-reveal-group>
            @foreach($cities as $city)
                <a class="card card-link tile" href="{{ route('city', $city->slug) }}" data-reveal>
                    <span class="flex items-center gap-2.5">
                        <span class="usp-icon" style="width:36px;height:36px;border-radius:10px">@include('partials.icon', ['name' => 'pin', 'size' => 18])</span>
                        <span class="tile-title">{{ $city->name }}@if($d = $cityDelivery[$city->id] ?? null)<em class="tile-note">{{ $d }}</em>@endif</span>
                    </span>
                    <span class="tile-sub">{{ $city->card_subtitle }} @include('partials.icon', ['name' => 'arrow-right', 'size' => 18])</span>
                </a>
            @endforeach
        </div>
    </div>
</section>

@if($reviews->isNotEmpty())
<section class="section section-tight" aria-labelledby="h-reviews">
    <div class="container-x">
        <div class="section-head" data-reveal>
            <div>
                <h2 id="h-reviews">{{ Setting::get('home_reviews_title') ?: 'Отзывы клиентов' }}</h2>
                <p class="text-muted" style="margin:6px 0 0;display:flex;gap:8px;align-items:center">
                    @include('partials.stars', ['value' => $reviewsSummary['avg']])
                    {{ number_format($reviewsSummary['avg'], 1, ',', '') }} · {{ $reviewsSummary['count'] }} {{ trans_choice('отзыв|отзыва|отзывов', $reviewsSummary['count']) }}
                </p>
            </div>
            <a class="link-arrow" href="{{ route('reviews') }}">Все отзывы @include('partials.icon', ['name' => 'arrow-right', 'size' => 18])</a>
        </div>
        <div class="reviews-grid">
            @foreach($reviews as $review)
                @include('partials.review-card', ['review' => $review, 'reveal' => true])
            @endforeach
        </div>
    </div>
</section>
@endif

@if(isset($faqs) && $faqs->isNotEmpty())
<section class="section section-tight" aria-labelledby="h-faq">
    @php($faqIcons = ['rules' => 'id-card', 'booking' => 'calendar', 'deposit' => 'wallet', 'pickup' => 'pin', 'trip' => 'route', 'incidents' => 'shield', 'business' => 'briefcase'])
    <div class="container-x faq-home">
        <div class="faq-side">
            <div class="faq-side-head" data-reveal>
                <h2 id="h-faq" style="margin:0 0 12px">Частые вопросы</h2>
                <p class="text-muted" style="margin:0">{{ Setting::get('home_faq_lead', 'Коротко о залоге, доставке и подтверждении заявки.') }}</p>
            </div>
            <div class="faq-side-extra">
                @if(!empty($faqGroups))
                    <nav class="faq-topics" aria-label="Темы вопросов" data-rail>
                        @foreach($faqGroups as $g)
                            <a href="{{ route('faq') }}#g-{{ $g['key'] }}">
                                <span class="faq-topic-icon">@include('partials.icon', ['name' => $faqIcons[$g['key']] ?? 'chat', 'size' => 16])</span>
                                <span class="faq-topic-label">{{ $g['label'] }}</span>
                                <span class="faq-topic-count">{{ $g['count'] }}</span>
                            </a>
                        @endforeach
                    </nav>
                    <a class="link-arrow faq-all" href="{{ route('faq') }}">Все вопросы — {{ array_sum(array_column($faqGroups, 'count')) }} @include('partials.icon', ['name' => 'arrow-right', 'size' => 18])</a>
                @endif
                <div class="faq-help">
                    <p class="faq-help-title">{{ Setting::get('faq_more_title') ?: 'Не нашли ответ?' }}</p>
                    <p class="note" style="margin:0 0 12px">{{ Setting::get('faq_more_text') ?: 'Спросите нас — отвечаем круглосуточно, обычно за пару минут.' }}</p>
                    @include('partials.messengers', ['variant' => 'grid', 'text' => 'Здравствуйте! У меня вопрос по аренде.'])
                    <a class="faq-help-phone" href="tel:{{ $phoneRaw }}">@include('partials.icon', ['name' => 'phone', 'size' => 16]) {{ $phone }}</a>
                </div>
            </div>
        </div>
        <div class="faq-main">
            @include('partials.faq-list', ['reveal' => true])
        </div>
    </div>
</section>
@endif

<section class="section section-tight">
    <div class="container-x">
        <div class="cta-band" data-reveal>
            <div>
                <h2>{{ Setting::get('cta_title', 'Не знаете, что выбрать?') }}</h2>
                <p>{{ Setting::get('cta_text', 'Ответьте на 6 коротких вопросов — подберём машину и объясним выбор. Или позвоните, подберём по телефону.') }}</p>
            </div>
            <div class="cta-band-actions">
                <a class="btn btn-accent btn-lg" href="{{ route('quiz') }}">Подобрать авто</a>
                <a class="btn btn-on-dark btn-lg" href="tel:{{ $phoneRaw }}">@include('partials.icon', ['name' => 'phone', 'size' => 18]) {{ $phone }}</a>
            </div>
        </div>
    </div>
</section>
@endsection

@push('sticky')
{{-- Телефон: главное действие под большим пальцем, появляется, когда форма первого экрана ушла из вида --}}
<div class="sticky-bar home-bar is-hidden" data-home-bar>
    <div class="flex items-center gap-2">
        <a class="btn btn-accent flex-1 min-w-0" href="{{ route('quiz') }}">Подобрать машину</a>
        <a class="icon-btn" href="#panel-dates" data-home-bar-dates aria-label="Даты и место">@include('partials.icon', ['name' => 'calendar', 'size' => 20])</a>
        <button type="button" class="icon-btn" data-fab-open aria-label="{{ \App\Models\Setting::get('fab_label', 'Написать нам') }}" aria-expanded="false">@include('partials.icon', ['name' => 'chat', 'size' => 20])</button>
    </div>
</div>
@endpush
