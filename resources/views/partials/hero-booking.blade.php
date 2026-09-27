{{-- Hero: «Где и когда» — главный путь к машине, как у крупных прокатов. Вторая вкладка — умный поиск по названию. --}}
@php
    use App\Models\Setting;
    $places = \App\Support\Places::all();
    $default = collect($places)->first(fn ($p) => $p['popular']) ?? ($places[0] ?? null);
@endphp
@include('partials.places-data')
<div class="hero-tabs" role="tablist" aria-label="Способ поиска" data-hero-tabs>
    <button type="button" role="tab" id="tab-dates" aria-controls="panel-dates" aria-selected="true">@include('partials.icon', ['name' => 'calendar', 'size' => 16]) {{ Setting::get('hero_tab_dates') ?: 'По датам и месту' }}</button>
    <button type="button" role="tab" id="tab-name" aria-controls="panel-name" aria-selected="false" tabindex="-1">@include('partials.icon', ['name' => 'search', 'size' => 16]) {{ Setting::get('hero_tab_name') ?: 'По названию' }}</button>
</div>
<form class="card search-card hero-book" id="panel-dates" role="tabpanel" aria-labelledby="tab-dates" method="get" action="{{ route('catalog') }}" data-hero-book>
    <div class="hero-book-place">
        <label class="field-label" for="hb-from">{{ Setting::get('hero_place_label') ?: 'Где забрать' }}</label>
        <select class="field" id="hb-from" name="from" data-place-picker>
            @foreach($places as $p)
                <option value="{{ $p['id'] }}" @selected($default && $p['id'] === $default['id'])>{{ $p['group'] }}{{ $p['label'] ? ', '.$p['label'] : '' }}</option>
            @endforeach
        </select>
        <label class="hero-book-other"><input type="checkbox" data-other-return> {{ Setting::get('hero_other_return') ?: 'Вернуть в другом месте' }}</label>
        <div class="hero-book-return" data-return-place hidden>
            <label class="field-label" for="hb-to">Где вернуть</label>
            <select class="field" id="hb-to" name="to" data-place-picker disabled>
                @foreach($places as $p)
                    <option value="{{ $p['id'] }}" @selected($default && $p['id'] === $default['id'])>{{ $p['group'] }}{{ $p['label'] ? ', '.$p['label'] : '' }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="daterange-fields hero-dates" data-daterange data-min-days="1">
        <div><label class="field-label" for="hb-start">Начало</label><input class="field" id="hb-start" type="datetime-local" name="starts_at" value="{{ now()->addDay()->format('Y-m-d\T10:00') }}" required></div>
        <div><label class="field-label" for="hb-end">Окончание</label><input class="field" id="hb-end" type="datetime-local" name="ends_at" value="{{ now()->addDays(4)->format('Y-m-d\T10:00') }}" required></div>
    </div>
    <button class="btn btn-accent btn-lg" type="submit">{{ Setting::get('hero_book_submit') ?: 'Показать машины' }}</button>
</form>
