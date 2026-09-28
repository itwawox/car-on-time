@extends('layouts.app')

@section('content')
@php($betterLabel = \App\Models\Setting::get('compare_better') ?: 'лучше')
<section class="page-head">
    <div class="container-x">
        @include('partials.breadcrumbs')
        <h1>{{ \App\Support\Seo\SeoSettings::meta('compare', 'h1') }}</h1>
        @if($intro = \App\Support\Seo\SeoSettings::meta('compare', 'intro'))<p style="color:var(--color-muted);max-width:720px;margin:8px 0 0">{{ $intro }}</p>@endif
    </div>
</section>

<section class="section" style="padding-top:24px" data-compare-page data-ids="{{ $cars->pluck('id')->implode(',') }}">
    <div class="container-x">
        @if($cars->isEmpty())
            <div class="card card-pad" style="text-align:center">
                <p style="margin:0;font-weight:600">Пока нечего сравнивать</p>
                <p class="note" style="margin:6px 0 16px">Нажмите «Сравнить» на карточках машин в каталоге — до {{ \App\Http\Controllers\CompareController::LIMIT }} автомобилей.</p>
                <a class="btn btn-primary" href="{{ route('catalog') }}">Перейти в каталог</a>
            </div>
        @else
            @if($cars->count() === 1)
                <p class="compare-need-more">
                    @include('partials.icon', ['name' => 'plus', 'size' => 16])
                    {{ \App\Models\Setting::get('compare_page_need_more') ?: 'Добавьте ещё хотя бы одну машину — сравнивать можно от двух.' }}
                    <a href="{{ route('catalog') }}">Выбрать в каталоге</a>
                </p>
            @endif
            <div class="compare-tools">
                <label class="check"><input type="checkbox" data-compare-diff> Только отличия</label>
                <button type="button" class="clear-btn" data-compare-clear data-tooltip="{{ \App\Models\Setting::get('compare_clear_hint') ?: 'Убрать все машины из сравнения' }}">@include('partials.icon', ['name' => 'close', 'size' => 14, 'stroke' => 2.2]) {{ \App\Models\Setting::get('compare_clear') ?: 'Очистить сравнение' }}</button>
            </div>
            <div class="compare-scroll" data-rail data-rail-free>
                <table class="compare-table" style="--cols: {{ $cars->count() }}">
                    <thead>
                        <tr>
                            <th scope="col"><span class="sr-only">Характеристика</span></th>
                            @foreach($cars as $car)
                                <th scope="col">
                                    <div class="compare-car">
                                        <button type="button" class="compare-remove" data-compare-remove="{{ $car->id }}" aria-label="Убрать {{ $car->displayName() }} из сравнения">@include('partials.icon', ['name' => 'close', 'size' => 16])</button>
                                        <a href="{{ route('car.show', $car->slug) }}" class="compare-car-media" data-skeleton>
                                            @if($cover = $car->coverUrl('card'))
                                                <img src="{{ $cover }}" alt="{{ $car->coverAlt() }}" width="640" height="353" loading="lazy" decoding="async">
                                            @endif
                                        </a>
                                        <a href="{{ route('car.show', $car->slug) }}" class="compare-car-name">{{ $car->displayName() }}</a>
                                    </div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($table as $row)
                            <tr @if($row['same']) data-same @endif>
                                <th scope="row">@include('partials.icon', ['name' => $row['icon'], 'size' => 16]) {{ $row['label'] }}</th>
                                @foreach($row['values'] as $i => $value)
                                    <td @class(['is-best' => $row['best'] === $i])>
                                        {{ $value }}
                                        @if($row['best'] === $i)<span class="compare-best">{{ $betterLabel }}</span>@endif
                                        {{-- Полоска разницы: длина — доля от наибольшего значения в строке --}}
                                        @if(($row['bars'][$i] ?? null) !== null)<span class="compare-bar-line" style="--bar: {{ $row['bars'][$i] }}" aria-hidden="true"></span>@endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                        <tr class="compare-actions">
                            <th scope="row"><span class="sr-only">Действия</span></th>
                            @foreach($cars as $car)
                                <td><a class="btn btn-accent btn-sm btn-block" href="{{ route('car.show', $car->slug) }}#h-quote">Оставить заявку</a></td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</section>
@endsection
