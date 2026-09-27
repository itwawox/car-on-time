{{-- Панель фильтров каталога: шторка снизу на телефоне, панель справа на компьютере. Без JS — обычная форма. --}}
@php
    $f = $filters ?? [];
    $fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
    [$pMin, $pMax] = $priceRange;
    $step = 100;
    $pMin = (int) floor($pMin / $step) * $step;
    $pMax = (int) ceil($pMax / $step) * $step;
    $fuels = array_intersect_key(\App\Models\Car::FUELS, array_flip(collect(\App\Support\Fleet::all())->pluck('fuel')->filter()->unique()->values()->all()));
    $brandsUsed = collect(\App\Support\Fleet::all())->pluck('brand')->unique()->all();
@endphp
<dialog class="filters" id="filters" aria-labelledby="filters-title" data-filters data-count-url="{{ route('catalog.count') }}">
    <form method="get" action="{{ url()->current() }}" class="filters-form">
        @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
        <header class="filters-head">
            <h2 id="filters-title">Фильтры</h2>
            <button type="button" class="icon-btn" data-filters-close aria-label="Закрыть">@include('partials.icon', ['name' => 'close', 'size' => 20])</button>
        </header>
        <div class="filters-body">
            <fieldset>
                <legend>Цена за сутки, ₽</legend>
                <div class="range" data-range data-min="{{ $pMin }}" data-max="{{ $pMax }}">
                    <input type="range" min="{{ $pMin }}" max="{{ $pMax }}" step="{{ $step }}" value="{{ $f['price_min'] ?? $pMin }}" aria-label="Цена от" data-range-from>
                    <input type="range" min="{{ $pMin }}" max="{{ $pMax }}" step="{{ $step }}" value="{{ $f['price_max'] ?? $pMax }}" aria-label="Цена до" data-range-to>
                </div>
                <div class="range-inputs">
                    <label><span>от</span><input class="field" type="number" name="price_min" inputmode="numeric" min="0" step="{{ $step }}" placeholder="{{ $fmt($pMin) }}" value="{{ $f['price_min'] ?? '' }}"></label>
                    <label><span>до</span><input class="field" type="number" name="price_max" inputmode="numeric" min="0" step="{{ $step }}" placeholder="{{ $fmt($pMax) }}" value="{{ $f['price_max'] ?? '' }}"></label>
                </div>
            </fieldset>
            <fieldset>
                <legend>Коробка</legend>
                <div class="seg">
                    @foreach(['' => 'Любая', 'at' => 'Автомат', 'mt' => 'Механика'] as $v => $label)
                        <label><input type="radio" name="kp" value="{{ $v }}" @checked(($f['kp'] ?? ($meta['gearbox'] ?? '')) === $v)><span>{{ $label }}</span></label>
                    @endforeach
                </div>
            </fieldset>
            <fieldset>
                <legend>Мест</legend>
                <div class="seg">
                    @foreach(['' => 'Любое', '5' => 'от 5', '7' => 'от 7', '8' => 'от 8'] as $v => $label)
                        <label><input type="radio" name="seats" value="{{ $v }}" @checked((string) ($f['seats'] ?? '') === (string) $v)><span>{{ $label }}</span></label>
                    @endforeach
                </div>
            </fieldset>
            <fieldset>
                <legend>Привод и топливо</legend>
                <div class="chips-check">
                    <label><input type="checkbox" name="awd" value="1" @checked(!empty($f['awd']))><span>Полный привод</span></label>
                    @foreach($fuels as $v => $label)
                        <label><input type="radio" name="fuel" value="{{ $v }}" @checked(($f['fuel'] ?? '') === $v)><span>{{ $label }}</span></label>
                    @endforeach
                </div>
            </fieldset>
            <fieldset>
                <legend>Класс</legend>
                <div class="chips-check">
                    @foreach($classes as $class)
                        <label><input type="checkbox" name="class[]" value="{{ $class->slug }}" @checked(in_array($class->slug, $f['class'] ?? [], true) || ($meta['class']->slug ?? null) === $class->slug)><span>{{ $class->name }}</span></label>
                    @endforeach
                </div>
            </fieldset>
            <fieldset>
                <legend>Кузов</legend>
                <div class="chips-check">
                    @foreach($bodies as $body)
                        <label><input type="checkbox" name="body[]" value="{{ $body->slug }}" @checked(in_array($body->slug, $f['body'] ?? [], true) || ($meta['body']->slug ?? null) === $body->slug)><span>{{ $body->name }}</span></label>
                    @endforeach
                </div>
            </fieldset>
            <fieldset>
                <legend>Марка</legend>
                <input class="field filters-brand-search" type="search" placeholder="Найти марку" aria-label="Найти марку" data-brand-search>
                <div class="chips-check" data-brand-list>
                    @foreach($brands->whereIn('id', $brandsUsed) as $brand)
                        <label data-brand="{{ mb_strtolower($brand->name) }}"><input type="checkbox" name="brand[]" value="{{ $brand->slug }}" @checked(in_array($brand->slug, $f['brand'] ?? [], true) || ($meta['brand']->slug ?? null) === $brand->slug)><span>{{ $brand->name }}</span></label>
                    @endforeach
                </div>
            </fieldset>
        </div>
        <footer class="filters-foot">
            <a class="btn btn-outline" href="{{ url()->current() }}" data-filters-reset>Сбросить</a>
            <button class="btn btn-primary" type="submit" data-filters-submit>Показать</button>
        </footer>
    </form>
</dialog>
