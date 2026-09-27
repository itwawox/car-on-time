{{-- Подробный вид каталога: цифры для тех, кто сравнивает. Заголовки — сортировка (?sort=…, noindex) --}}
@php
    $fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
    $sortLink = fn (string $key) => request()->fullUrlWithQuery(['sort' => request('sort') === $key ? null : $key]);
    $current = request('sort');
@endphp
<div class="ctable" role="table" aria-label="Автомобили: характеристики и цены">
    <div class="ctable-row ctable-head" role="row">
        <span role="columnheader">Автомобиль</span>
        <span role="columnheader">Коробка · привод</span>
        <span role="columnheader">Мест</span>
        <a role="columnheader" href="{{ $sortLink('power') }}" rel="nofollow" @class(['is-sorted' => $current === 'power'])>Мощность</a>
        <a role="columnheader" href="{{ $sortLink('economy') }}" rel="nofollow" @class(['is-sorted' => $current === 'economy'])>Расход · 100 км</a>
        <a role="columnheader" href="{{ $sortLink('trunk') }}" rel="nofollow" @class(['is-sorted' => $current === 'trunk'])>Багажник</a>
        <a role="columnheader" href="{{ $sortLink('price') }}" rel="nofollow" @class(['is-sorted' => $current === 'price'])>Цена</a>
    </div>
    @foreach($cars as $car)
        @php($row = \App\Support\Fleet::get($car->id))
        @continue(! $row)
        <div class="ctable-row" role="row">
            <a class="ctable-car" role="cell" href="{{ $row['url'] }}">
                <span class="ctable-thumb" data-skeleton>@if($row['thumb'])<img src="{{ $row['thumb'] }}" alt="" width="96" height="53" loading="lazy" decoding="async">@endif</span>
                <span><b>{{ $row['name'] }}</b>@if($row['class_name'])<small>{{ $row['class_name'] }} · {{ $row['body_name'] }}</small>@endif</span>
            </a>
            <span role="cell" data-label="Коробка">{{ $row['fuel'] === 'electric' ? 'Электро' : ($row['gearbox'] === 'mt' ? 'Механика' : 'Автомат') }}{{ $row['drive'] === '4wd' ? ' · 4WD' : '' }}</span>
            <span role="cell" data-label="Мест">{{ $row['seats'] ?: '—' }}</span>
            <span role="cell" data-label="Мощность">{{ $row['power'] ? $row['power'].' л.с.' : '—' }}</span>
            <span role="cell" data-label="Расход">@if($row['consumption']){{ \App\Support\CarFacts::num($row['consumption']) }} {{ $row['unit'] }}@if($row['per100'])<small>≈ {{ $fmt($row['per100']) }} ₽/100 км</small>@endif @else — @endif</span>
            <span role="cell" data-label="Багажник">{{ $row['trunk'] ? $row['trunk'].' л' : '—' }}</span>
            <span role="cell" class="ctable-price" data-label="Цена" data-card-price="{{ $row['id'] }}">@if($row['price'])<span class="price-from">от</span>{{ $fmt($row['price']) }} ₽ <small>/ сутки</small>@else по запросу @endif</span>
        </div>
    @endforeach
</div>
<p class="note" style="margin-top:10px">Мощность, расход и багажник — паспортные данные модели (≈). Стоимость 100 км — по цене топлива из настроек сайта.</p>
