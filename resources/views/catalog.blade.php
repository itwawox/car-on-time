@extends('layouts.app', [
    'title' => $meta['title'],
    'description' => $meta['description'],
    'noindex' => $noindex ?? false,
    'canonical' => $canonical ?? url()->current(),
    'jsonld' => $jsonld ?? [],
])

@section('content')
<section class="page-head">
    <div class="container-x">
        @include('partials.breadcrumbs')
        <h1>{{ $meta['h1'] }}</h1>
        @if(!empty($meta['intro']))
            <p class="lead">{{ $meta['intro'] }}</p>
        @endif
    </div>
</section>

<section class="section" style="padding-top:24px">
    <div class="container-x">
        <div class="catalog-search">
            @include('partials.search-box', ['id' => 'catalog', 'label' => 'Поиск по каталогу', 'placeholder' => \App\Support\Search\SearchSettings::text('placeholder_catalog')])
        </div>

        <nav class="filter-bar" aria-label="Фильтры каталога" data-rail>
            @php $allActive = empty($meta['class']) && empty($meta['body']) && empty($meta['gearbox']) && empty($meta['brand']) && empty($meta['city']); @endphp
            <a class="filter" href="{{ route('catalog') }}" @if($allActive) aria-current="page" @endif>Все</a>
            @foreach($classes as $class)
                <a class="filter" href="{{ route('klass', $class->slug) }}" @if(($meta['class']->slug ?? '') === $class->slug) aria-current="page" @endif>{{ $class->name }}</a>
            @endforeach
            @foreach($bodies as $body)
                <a class="filter" href="{{ route('kuzov', $body->slug) }}" @if(($meta['body']->slug ?? '') === $body->slug) aria-current="page" @endif>{{ $body->name }}</a>
            @endforeach
            <a class="filter" href="{{ route('korobka', 'avtomat') }}" @if(($meta['gearbox'] ?? '') === 'at') aria-current="page" @endif>Автомат</a>
            <a class="filter" href="{{ route('korobka', 'mehanika') }}" @if(($meta['gearbox'] ?? '') === 'mt') aria-current="page" @endif>Механика</a>
        </nav>

        <div class="catalog-toolbar">
            <p class="m-0 font-semibold">Найдено {{ $cars->total() }} авто</p>
            <button type="button" class="btn btn-outline btn-sm filters-open" data-filters-open aria-haspopup="dialog" aria-controls="filters">
                @include('partials.icon', ['name' => 'filter', 'size' => 16]) Фильтры@if(count($filterChips))<span class="filters-count">{{ count($filterChips) }}</span>@endif
            </button>
            @include('partials.places-data')
            <span class="place-chip" data-place-chip hidden>@include('partials.icon', ['name' => 'pin', 'size' => 14]) <span data-place-chip-text></span><button type="button" aria-label="Убрать место выдачи" data-place-chip-clear>@include('partials.icon', ['name' => 'close', 'size' => 12])</button></span>
            <div class="catalog-dates" data-catalog-dates data-busy-text="{{ \App\Models\Setting::get('availability_busy_card') ?: 'Занята на эти даты' }}">
                {{-- Даты для цен «за ваши даты»: без JS — просто поля, с JS — календарь периода --}}
                <div class="daterange-fields catalog-daterange" data-daterange data-min-days="1" data-compact>
                    <div><label class="field-label" for="c-start">Начало</label><input class="field" id="c-start" type="datetime-local" name="starts_at"></div>
                    <div><label class="field-label" for="c-end">Окончание</label><input class="field" id="c-end" type="datetime-local" name="ends_at"></div>
                </div>
            </div>
            <label class="catalog-sort">
                <span class="sr-only">Сортировка</span>
                <select class="field" onchange="location.href=this.value">
                    <option value="{{ request()->fullUrlWithQuery(['sort' => null]) }}">Сначала популярные</option>
                    @foreach(\App\Support\CatalogListing::SORTS as $key => [$label])
                        <option value="{{ request()->fullUrlWithQuery(['sort' => $key]) }}" @selected(request('sort') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <div class="view-toggle" role="group" aria-label="Вид каталога">
                <button type="button" data-view-set="cards" aria-pressed="true">@include('partials.icon', ['name' => 'grid', 'size' => 16]) Карточки</button>
                <button type="button" data-view-set="table" aria-pressed="false">@include('partials.icon', ['name' => 'compare', 'size' => 16]) Подробно</button>
            </div>
        </div>

        @if(count($filterChips))
            <div class="filter-chips" aria-label="Выбранные фильтры">
                @foreach($filterChips as $chip)
                    @php
                        $q = request()->except(['page']);
                        if ($chip['key'] === 'price') { unset($q['price_min'], $q['price_max']); }
                        elseif ($chip['value'] !== null) { $q[$chip['key']] = array_values(array_diff((array) ($q[$chip['key']] ?? []), [$chip['value']])); }
                        else { unset($q[$chip['key']]); }
                    @endphp
                    <a class="filter-chip" href="{{ url()->current().($q ? '?'.http_build_query($q) : '') }}" rel="nofollow" aria-label="Убрать фильтр {{ $chip['label'] }}">{{ $chip['label'] }} @include('partials.icon', ['name' => 'close', 'size' => 12])</a>
                @endforeach
                <a class="filter-chip-reset" href="{{ url()->current() }}" rel="nofollow">Сбросить всё</a>
            </div>
        @endif

        @if($cars->isEmpty() && count($filterChips))
            <div class="card card-pad text-center">
                <p class="m-0 mb-2 font-semibold">Под эти фильтры машин нет</p>
                <p class="note m-0 mb-4">Уберите один из фильтров или напишите нам — подберём похожую машину.</p>
                <a class="btn btn-primary" href="{{ url()->current() }}">Сбросить фильтры</a>
            </div>
        @elseif($cars->isEmpty())
            <div class="card card-pad text-center">
                <p class="m-0 mb-4 font-semibold">В этом разделе пока нет машин.</p>
                <a class="btn btn-primary" href="{{ route('catalog') }}">Смотреть весь каталог</a>
            </div>
        @else
            <div class="catalog-views" data-catalog-views data-ids="{{ $cars->pluck('id')->implode(',') }}">
                <div class="car-grid catalog-view-cards" data-reveal-group>
                    @foreach($cars as $car)
                        @include('partials.car-card', ['car' => $car, 'heading' => 'h2', 'eager' => $loop->first && $cars->onFirstPage(), 'reveal' => $loop->index >= 4 ? true : null])
                    @endforeach
                </div>
                <div class="catalog-view-table">
                    @include('partials.catalog-table')
                </div>
            </div>
        @endif

        {{ $cars->onEachSide(1)->links() }}

        @if(!empty($meta['city']) && $cars->onFirstPage())
            @include('partials.map-embed', ['id' => 'city', 'title' => 'Доставка: '.$meta['city']->name.' на карте', 'points' => [
                ['label' => $meta['city']->name, 'query' => $meta['city']->name.', Республика Крым', 'url' => $meta['city']->map_url],
            ]])
        @endif

        @include('partials.memory-blocks', ['id' => 'catalog'])
        @include('partials.filters')

        @if(!empty($meta['seo_text']) && $cars->onFirstPage())
            <article class="card card-pad prose-body" style="margin-top:48px">
                {!! \App\Support\CmsHtml::clean($meta['seo_text']) !!}
            </article>
        @endif
    </div>
</section>
@endsection

@push('head')
    @if (! $cars->onFirstPage())
        <link rel="prev" href="{{ $cars->previousPageUrl() }}">
    @endif
    @if ($cars->hasMorePages())
        <link rel="next" href="{{ $cars->nextPageUrl() }}">
    @endif
@endpush
