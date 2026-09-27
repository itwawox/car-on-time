@php
    use App\Support\Search\SearchSettings;

    $t = SearchSettings::text(...);
    $removeUrl = fn (array $except) => route('search', array_filter(array_diff_key(['q' => $query, ...$filters], array_flip($except))));
    $tips = (array) (SearchSettings::ui()['page_empty_tips'] ?? []);
@endphp
@extends('layouts.app', [
    'title' => $t('page_title', ['query' => $query]),
    'description' => $t('page_description', ['query' => $query]),
    'noindex' => true,
    'canonical' => route('search', ['q' => $query]),
])

@section('content')
<section class="page-head">
    <div class="container-x">
        @include('partials.breadcrumbs', ['crumbs' => [
            ['name' => 'Главная', 'url' => route('home')],
            ['name' => 'Каталог', 'url' => route('catalog')],
            ['name' => 'Поиск'],
        ]])
        <h1>
            {{ $cars->total() ? $t('page_found', ['count' => $cars->total()]) : $t('page_not_found') }}
            <span class="text-muted" style="font-weight:500">{{ $t('page_query', ['query' => $query]) }}</span>
        </h1>
    </div>
</section>

<section class="section" style="padding-top:20px">
    <div class="container-x">
        <div class="catalog-search">
            @include('partials.search-box', ['id' => 'page', 'value' => $query, 'label' => 'Поиск по каталогу'])
        </div>

        @if(!empty($meta['corrected']))
            <p class="sbox-notice" style="display:inline-block;margin:0 0 12px">{{ $t('page_corrected', ['query' => $meta['corrected']]) }}</p>
        @elseif(!empty($meta['relaxed']))
            <p class="sbox-notice" style="display:inline-block;margin:0 0 12px">{{ $t('page_relaxed') }}</p>
        @endif

        @if(!empty($meta['chips']) || $filters)
            <div class="understood" style="margin-bottom:20px">
                <span class="note" style="margin:0">{{ $t('page_understood') }}</span>
                @foreach($meta['chips'] as $chip)
                    <span class="understood-chip">{{ $chip['label'] }}</span>
                @endforeach
                @if(!empty($filters['class']))
                    <span class="understood-chip">
                        {{ $classes->firstWhere('slug', $filters['class'])?->name }}
                        <a href="{{ $removeUrl(['class']) }}" aria-label="Убрать фильтр по классу">@include('partials.icon', ['name' => 'close', 'size' => 14])</a>
                    </span>
                @endif
                @if(!empty($filters['kp']))
                    <span class="understood-chip">
                        {{ $t($filters['kp'] === 'at' ? 'chip_at' : 'chip_mt') }}
                        <a href="{{ $removeUrl(['kp']) }}" aria-label="Убрать фильтр по коробке">@include('partials.icon', ['name' => 'close', 'size' => 14])</a>
                    </span>
                @endif
            </div>
        @endif

        @if($cars->isEmpty())
            <div class="card card-pad" style="max-width:720px">
                <p class="m-0 font-semibold" style="font-size:1.0625rem">{{ $t('page_empty_title') }}</p>
                @if($tips)
                    <ul class="note" style="margin:8px 0 16px;padding-left:1.1em;list-style:disc">
                        @foreach($tips as $tip)
                            <li>{{ $tip }}</li>
                        @endforeach
                    </ul>
                @endif
                @if($popular)
                    <p class="field-label">{{ $t('group_popular') }}</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($popular as $brand)
                            <a class="filter" href="{{ $brand['url'] }}">{{ $brand['title'] }} <span class="text-muted" style="margin-left:6px;font-weight:500">{{ $brand['count'] }}</span></a>
                        @endforeach
                    </div>
                @endif
                <p style="margin:20px 0 0"><a class="btn btn-primary" href="{{ route('catalog') }}">{{ $t('page_catalog_button') }}</a></p>
            </div>
        @else
            <div class="car-grid" data-reveal-group>
                @foreach($cars as $car)
                    @include('partials.car-card', ['car' => $car, 'heading' => 'h2', 'eager' => $loop->first, 'reveal' => $loop->index >= 4 ? true : null])
                @endforeach
            </div>
            {{ $cars->onEachSide(1)->links() }}
        @endif
    </div>
</section>
@endsection
