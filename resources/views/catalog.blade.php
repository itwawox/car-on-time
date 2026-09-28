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
            {{-- Вводный текст — одной строкой, целиком по «Подробнее»: до машин меньше прокрутки --}}
            <p class="lead catalog-lead" data-lead>{{ $meta['intro'] }}</p>
            <button type="button" class="catalog-lead-more" data-lead-more hidden>{{ \App\Models\Setting::get('catalog_lead_more') ?: 'Подробнее' }}</button>
        @endif
    </div>
</section>

<section class="section" style="padding-top:16px">
    <div class="container-x">
        {{-- Командная строка: поиск · даты · сортировка · фильтры · вид. На компьютере прилипает под шапкой, на телефоне действия — плавающей панелью внизу --}}
        <div class="cmdbar" data-cmdbar>
            <div class="catalog-search">
                @include('partials.search-box', ['id' => 'catalog', 'label' => 'Поиск по каталогу', 'placeholder' => \App\Support\Search\SearchSettings::text('placeholder_catalog')])
            </div>
            <div class="cmdbar-actions">
                <div class="catalog-dates" data-catalog-dates data-busy-text="{{ \App\Models\Setting::get('availability_busy_card') ?: 'Занята на эти даты' }}">
                    {{-- Даты для цен «за ваши даты»: без JS — просто поля, с JS — календарь периода --}}
                    <div class="daterange-fields catalog-daterange" data-daterange data-min-days="1" data-compact>
                        <div><label class="field-label" for="c-start">Начало</label><input class="field" id="c-start" type="datetime-local" name="starts_at"></div>
                        <div><label class="field-label" for="c-end">Окончание</label><input class="field" id="c-end" type="datetime-local" name="ends_at"></div>
                    </div>
                </div>
                <label class="catalog-sort" title="Сортировка">
                    <span class="sr-only">Сортировка</span>
                    <span class="catalog-sort-icon" aria-hidden="true">@include('partials.icon', ['name' => 'sort', 'size' => 18])</span>
                    <select class="field" onchange="location.href=this.value">
                        <option value="{{ request()->fullUrlWithQuery(['sort' => null]) }}">Сначала популярные</option>
                        @foreach(\App\Support\CatalogListing::SORTS as $key => [$label])
                            <option value="{{ request()->fullUrlWithQuery(['sort' => $key]) }}" @selected(request('sort') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="button" class="btn btn-outline btn-sm filters-open" data-filters-open aria-haspopup="dialog" aria-controls="filters">
                    @include('partials.icon', ['name' => 'filter', 'size' => 16]) Фильтры@if(count($filterChips))<span class="filters-count">{{ count($filterChips) }}</span>@endif
                </button>
                @include('partials.catalog-view-toggle')
            </div>
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

        <div class="catalog-meta">
            <p class="m-0 font-semibold">Найдено {{ $cars->total() }} авто</p>
            @include('partials.places-data')
            <span class="place-chip" data-place-chip hidden>@include('partials.icon', ['name' => 'pin', 'size' => 14]) <span data-place-chip-text></span><button type="button" aria-label="Убрать место выдачи" data-place-chip-clear>@include('partials.icon', ['name' => 'close', 'size' => 12])</button></span>
            @include('partials.catalog-view-toggle')
        </div>

        @if(count($filterChips))
            <div class="filter-chips" aria-label="Выбранные фильтры">
                @foreach($filterChips as $chip)
                    <a class="filter-chip" href="{{ \App\Support\CatalogFilters::urlWithout(request(), $chip) }}" rel="nofollow" aria-label="Убрать фильтр {{ $chip['label'] }}">{{ $chip['label'] }} @include('partials.icon', ['name' => 'close', 'size' => 12])</a>
                @endforeach
                <a class="filter-chip-reset" href="{{ url()->current() }}" rel="nofollow">Сбросить всё</a>
            </div>
        @endif

        @if($cars->isEmpty() && count($filterChips))
            <div class="card card-pad text-center catalog-empty">
                <p class="m-0 mb-2 font-semibold">Под эти фильтры машин нет</p>
                @if(!empty($relax))
                    {{-- Подсказка вместо тупика: какое одно условие убрать, и сколько машин тогда найдётся --}}
                    <p class="note m-0 mb-4">{{ \App\Models\Setting::get('catalog_relax_text') ?: 'Уберите одно условие — машины найдутся:' }}</p>
                    <div class="relax-list">
                        @foreach($relax as $r)
                            <a class="relax" href="{{ $r['url'] }}" rel="nofollow">Убрать «{{ $r['label'] }}» <b>→ {{ $r['count'] }} {{ trans_choice('машина|машины|машин', $r['count']) }}</b></a>
                        @endforeach
                    </div>
                    <a class="catalog-empty-reset" href="{{ url()->current() }}" rel="nofollow">Сбросить все фильтры</a>
                @else
                    <p class="note m-0 mb-4">Уберите один из фильтров или напишите нам — подберём похожую машину.</p>
                    <a class="btn btn-primary" href="{{ url()->current() }}">Сбросить фильтры</a>
                @endif
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
                        @include('partials.car-card', ['car' => $car, 'titleTag' => 'h2', 'eager' => $loop->first && $cars->onFirstPage(), 'reveal' => $loop->index >= 4 ? true : null])
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
