@php
    $cover = $article->coverUrl('1200');
    $published = $article->published_at ?? $article->created_at;
@endphp
@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
    'ogType' => 'article',
    'ogImage' => $ogImage,
    'jsonld' => $jsonld,
])

@push('head')
    @if($published)<meta property="article:published_time" content="{{ $published->toAtomString() }}">@endif
    <meta property="article:modified_time" content="{{ $article->updated_at->toAtomString() }}">
    @if($article->category)<meta property="article:section" content="{{ $article->category }}">@endif
@endpush

@section('content')
<article>
    <header class="page-head">
        <div class="container-x">
            @include('partials.breadcrumbs')
            @if($article->category)<span class="eyebrow" style="margin-bottom:12px">{{ $article->category }}</span>@endif
            <h1>{{ $article->h1 ?: $article->title }}</h1>
            @if($article->excerpt)<p class="lead">{{ $article->excerpt }}</p>@endif
            <p class="note" style="margin:16px 0 0;display:flex;flex-wrap:wrap;gap:6px 14px">
                @if($article->author_name)<span>{{ $article->author_name }}@if($article->author_role), {{ $article->author_role }}@endif</span>@endif
                @if($published)<span>Опубликовано <time datetime="{{ $published->toDateString() }}">{{ $published->translatedFormat('j F Y') }}</time></span>@endif
                @if($article->updated_at->gt($published?->copy()->addDay() ?? $article->updated_at))<span>Обновлено <time datetime="{{ $article->updated_at->toDateString() }}">{{ $article->updated_at->translatedFormat('j F Y') }}</time></span>@endif
                <span>{{ $article->readingMinutes() }} мин чтения</span>
            </p>
        </div>
    </header>

    <div class="container-x page-layout" style="padding-block:24px 0">
        <div class="page-main min-w-0">
        @if($cover)
            <img class="article-cover" src="{{ $cover }}" alt="{{ $article->cover_alt ?: $article->title }}" width="1200" height="675" fetchpriority="high" decoding="async">
        @endif

        <div class="prose-body article-body">{!! \App\Support\CmsHtml::clean($body['html']) !!}</div>

        @if($faqs->isNotEmpty())
            <section style="margin-top:40px" aria-labelledby="h-article-faq">
                <h2 id="h-article-faq" style="margin:0 0 16px">Частые вопросы</h2>
                @include('partials.faq-list', ['faqs' => $faqs])
            </section>
        @endif
        </div>
        @include('partials.help-aside', ['toc' => $body['toc']])
    </div>
</article>

@if($cars->isNotEmpty())
    <section class="section" aria-labelledby="h-article-cars">
        <div class="container-x">
            <div class="section-head"><h2 id="h-article-cars">Подходящие машины</h2><a class="link-arrow" href="{{ route('search', ['q' => $article->cars_query]) }}">Все варианты @include('partials.icon', ['name' => 'arrow-right', 'size' => 18])</a></div>
            <div class="shelf" data-rail>
                @foreach($cars as $car)
                    @include('partials.car-card', ['car' => $car])
                @endforeach
            </div>
        </div>
    </section>
@endif

@if($related->isNotEmpty())
    <section class="section section-tight" aria-labelledby="h-related" style="{{ $cars->isEmpty() ? 'padding-top:48px' : '' }}">
        <div class="container-x">
            <div class="section-head"><h2 id="h-related">Читайте также</h2><a class="link-arrow" href="{{ route('articles') }}">Все статьи @include('partials.icon', ['name' => 'arrow-right', 'size' => 18])</a></div>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($related as $item)
                    @include('partials.article-card', ['article' => $item])
                @endforeach
            </div>
        </div>
    </section>
@endif
@endsection
