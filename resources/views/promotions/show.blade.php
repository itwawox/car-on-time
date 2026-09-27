@extends('layouts.app')

@section('content')
<section class="page-head">
    <div class="container-x">
        @include('partials.breadcrumbs')
        @if($promotion->badge)<span class="eyebrow">{{ $promotion->badge }}</span>@endif
        <h1>{{ $promotion->title }}</h1>
        @if($period = $promotion->periodLabel())<p class="note" style="margin:8px 0 0">Действует {{ $period }}</p>@endif
    </div>
</section>
<section class="section" style="padding-top:24px">
    <div class="container-x page-layout">
        <article class="page-main card card-pad prose-body">
            @if($cover = $promotion->coverUrl('1200'))
                <img class="article-cover" src="{{ $cover }}" alt="{{ $promotion->title }}" width="1200" height="675" fetchpriority="high">
            @endif
            @if($promotion->promo_code)
                <div class="promo-code-box">
                    <span>Промокод</span>
                    <button type="button" class="promo-code" data-copy="{{ $promotion->promo_code }}" title="Скопировать">{{ $promotion->promo_code }} @include('partials.icon', ['name' => 'check', 'size' => 14])</button>
                    <small>Укажите его в заявке — поле «Есть промокод?»</small>
                </div>
            @endif
            {!! \App\Support\CmsHtml::clean((string) $promotion->body) !!}
            <p><a class="btn btn-accent" href="{{ route('catalog') }}">Выбрать машину</a></p>
        </article>
        @include('partials.help-aside')
    </div>
</section>
@endsection
