@extends('layouts.app')

@section('content')
<section class="page-head">
    <div class="container-x">
        @include('partials.breadcrumbs')
        <h1>{{ $heading }}</h1>
        <p style="color:var(--color-muted);max-width:720px;margin:8px 0 0">{{ \App\Models\Setting::get('favorites_intro') ?: 'Машины, которые вы отметили сердечком. Список хранится только в вашем браузере — им можно поделиться ссылкой.' }}</p>
    </div>
</section>

<section class="section" style="padding-top:24px" data-favorites-page data-ids="{{ $cars->pluck('id')->implode(',') }}">
    <div class="container-x">
        @if($cars->isEmpty())
            <div class="card card-pad" style="text-align:center">
                <p style="margin:0;font-weight:600">Здесь пока пусто</p>
                <p class="note" style="margin:6px 0 16px">Нажмите сердечко на карточке машины — она появится здесь.</p>
                <a class="btn btn-primary" href="{{ route('catalog') }}">Перейти в каталог</a>
            </div>
        @else
            <div class="favorites-tools">
                <button type="button" class="btn btn-secondary btn-sm" data-share data-share-title="Мои машины — {{ \App\Models\Setting::get('brand_name', 'Car on Time') }}">@include('partials.icon', ['name' => 'share', 'size' => 16]) Поделиться списком</button>
                @if($cars->count() > 1)
                    @php($compareCount = min(\App\Http\Controllers\CompareController::LIMIT, $cars->count()))
                    <a class="btn btn-outline btn-sm" href="{{ route('compare', ['ids' => $cars->take($compareCount)->pluck('id')->implode(',')]) }}">@include('partials.icon', ['name' => 'compare', 'size' => 16]) {{ $cars->count() > $compareCount ? 'Сравнить первые '.$compareCount : 'Сравнить '.$compareCount.' '.trans_choice('машину|машины|машин', $compareCount) }}</a>
                @else
                    <a class="btn btn-hint btn-sm" href="{{ route('catalog') }}" data-tooltip="{{ \App\Models\Setting::get('compare_need_more_hint') ?: 'Сравнивать можно от двух машин — отметьте ещё одну сердечком или кнопкой «Сравнить»' }}">@include('partials.icon', ['name' => 'plus', 'size' => 16]) {{ \App\Models\Setting::get('compare_need_more') ?: 'Добавьте ещё машину для сравнения' }}</a>
                @endif
                <button type="button" class="clear-btn" data-favorites-clear data-tooltip="{{ \App\Models\Setting::get('favorites_clear_hint') ?: 'Убрать все машины из избранного' }}">@include('partials.icon', ['name' => 'close', 'size' => 14, 'stroke' => 2.2]) {{ \App\Models\Setting::get('favorites_clear') ?: 'Очистить избранное' }}</button>
            </div>
            <div class="car-grid">
                @foreach($cars as $car)
                    @include('partials.car-card', ['car' => $car])
                @endforeach
            </div>
        @endif
    </div>
</section>
@endsection
