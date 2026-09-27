@php use App\Models\Faq; use App\Models\Setting; @endphp
@extends('layouts.app', [
    'title' => \App\Support\Seo\SeoSettings::meta('faq', 'title'),
    'description' => \App\Support\Seo\SeoSettings::meta('faq', 'description'),
    'jsonld' => [app(\App\Services\Seo::class)->faq($faqs)],
])

@section('content')
<section class="page-head">
    <div class="container-x">
        @include('partials.breadcrumbs')
        <h1>{{ \App\Support\Seo\SeoSettings::meta('faq', 'h1') }}</h1>
        <p style="color:var(--color-muted);max-width:720px;margin:8px 0 0">{{ Setting::get('faq_intro') ?: 'Собрали ответы на вопросы, которые задают чаще всего: возраст и стаж, документы, залог, доставка, поездки по Крыму.' }}</p>
        <div class="faq-search">
            @include('partials.icon', ['name' => 'search', 'size' => 18])
            <input type="search" class="field" placeholder="{{ Setting::get('faq_search_placeholder') ?: 'Найти ответ: залог, 18 лет, мост, ночью…' }}" aria-label="Поиск по вопросам" data-faq-search autocomplete="off">
        </div>
    </div>
</section>

<section class="section" style="padding-top:20px" data-faq-page>
    <div class="container-x faq-layout">
        <nav class="faq-nav" aria-label="Разделы вопросов" data-rail>
            @foreach($groups as $key => $items)
                <a href="#g-{{ $key }}" data-faq-nav="{{ $key }}">{{ Faq::groupLabel($key) }} <span>{{ $items->count() }}</span></a>
            @endforeach
        </nav>

        <div class="faq-groups">
            @foreach($groups as $key => $items)
                <section class="faq-group" id="g-{{ $key }}" aria-labelledby="h-g-{{ $key }}" data-faq-group>
                    <h2 id="h-g-{{ $key }}">{{ Faq::groupLabel($key) }}</h2>
                    @include('partials.faq-list', ['faqs' => $items, 'copyLinks' => true])
                </section>
            @endforeach

            <div class="card card-pad faq-empty" data-faq-empty hidden>
                <p style="margin:0;font-weight:600">Не нашли такой вопрос</p>
                <p class="note" style="margin:6px 0 0">Напишите нам — ответим за пару минут.</p>
            </div>

            <div class="card card-pad faq-more">
                <div>
                    <h2>{{ Setting::get('faq_more_title') ?: 'Не нашли ответ?' }}</h2>
                    <p class="note" style="margin:4px 0 0">{{ Setting::get('faq_more_text') ?: 'Спросите нас — отвечаем круглосуточно, обычно за пару минут.' }}</p>
                </div>
                @include('partials.messengers', ['variant' => 'grid', 'text' => 'Здравствуйте! У меня вопрос по аренде.'])
            </div>
        </div>
    </div>
</section>
@endsection
