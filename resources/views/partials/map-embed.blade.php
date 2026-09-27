{{--
    Карта Яндекса без ключа API. Грузится только по кнопке — не тормозит страницу и не передаёт данные до клика.
    $points — [['label' => 'Офис', 'query' => 'адрес для поиска', 'url' => ссылка виджета или null], …]
--}}
@php
    $points = array_values(array_filter($points ?? [], fn ($p) => filled($p['query'] ?? null) || filled($p['url'] ?? null)));
    $src = fn ($p) => filled($p['url'] ?? null) ? $p['url'] : 'https://yandex.ru/map-widget/v1/?text='.rawurlencode($p['query']).'&z=13';
@endphp
@if($points)
    <section class="map-block" aria-labelledby="h-map-{{ $id ?? 'm' }}">
        <h2 id="h-map-{{ $id ?? 'm' }}">{{ $title ?? (\App\Models\Setting::get('map_title') ?: 'На карте') }}</h2>
        @if(count($points) > 1)
            <div class="map-tabs" role="group" aria-label="Точки на карте">
                @foreach($points as $i => $p)
                    <button type="button" data-map-point="{{ $src($p) }}" aria-pressed="{{ $i === 0 ? 'true' : 'false' }}">@include('partials.icon', ['name' => 'pin', 'size' => 14]) {{ $p['label'] }}</button>
                @endforeach
            </div>
        @endif
        <div class="map-frame" data-map data-src="{{ $src($points[0]) }}">
            <button type="button" class="map-placeholder" data-map-load>
                @include('partials.icon', ['name' => 'pin', 'size' => 28])
                <b>{{ \App\Models\Setting::get('map_button') ?: 'Показать карту' }}</b>
                <span>{{ $points[0]['label'] }}@if(filled($points[0]['query'] ?? null)) · {{ $points[0]['query'] }}@endif</span>
            </button>
        </div>
        <a class="map-open" href="https://yandex.ru/maps/?text={{ rawurlencode($points[0]['query'] ?? $points[0]['label']) }}" target="_blank" rel="noopener" data-map-open>Открыть в Яндекс.Картах @include('partials.icon', ['name' => 'arrow-right', 'size' => 14])</a>
    </section>
@endif
