@php
    use App\Models\Setting;

    $seoVars = ['name' => $car->seoName(), 'price' => $car->currentPriceFrom(), 'gearbox' => $car->gearboxLabel(), 'seats' => $car->seats];
    $title = $car->seo_title ?: \App\Support\Seo\SeoSettings::meta('car', 'title', $seoVars);
    $description = $car->seo_description ?: \App\Support\Seo\SeoSettings::meta('car', 'description', $seoVars);
    $h1 = \App\Support\Seo\SeoSettings::meta('car', 'h1', $seoVars) ?: $car->displayName();
    $wa = Setting::get('whatsapp');
    $tg = Setting::get('telegram');
    $phoneRaw = Setting::get('phone_raw');
    $msg = rawurlencode("Здравствуйте! Хочу {$car->displayName()}. Свободно?");
    $cover = $car->coverUrl('large');
    $from = $car->currentPriceFrom() ?: 0;
    $fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');

    // Характеристики: [иконка, название, значение, подсказка человеческим языком, типично для модели]; пустые не показываем
    $f = $car->facts();
    $per100 = $f->costPer100km();
    $specs = array_values(array_filter([
        ['gearbox', 'Коробка', $car->fuel === 'electric' ? 'Электропривод' : $car->gearboxLabel(), null, false],
        ['drive', 'Привод', $car->drivetrainLabel(), null, false],
        ['car', 'Кузов', $car->bodyType?->name, null, false],
        ['seats', 'Мест', $car->seats, null, false],
        ['bolt', 'Мощность', $f->powerLabel(), $f->powerHint(), $f->isTypical('power')],
        ['fuel', 'Расход', $f->consumptionLabel(), $per100 ? '≈ '.$fmt($per100).' ₽ на 100 км'.($f->gradeLabel() ? ' · '.$f->gradeLabel() : '') : $car->fuelLabel(), $f->isTypical('consumption')],
        ['bag', 'Багажник', $f->trunk ? $f->trunk.' л' : null, $f->trunkHint(), $f->isTypical('trunk')],
        ['clearance', 'Клиренс', $f->clearance ? $f->clearance.' мм' : null, $f->clearanceHint(), $f->isTypical('clearance')],
        ['engine', 'Двигатель', $f->engineLabel(), $f->isElectric() ? null : $car->fuelLabel(), $f->isTypical('engine')],
        ['calendar', 'Год', $car->yearsLabel(), null, false],
        ['wallet', 'Залог', $car->deposit ? $fmt($car->deposit).' ₽' : null, 'вернём после сдачи', false],
        ['clock', 'Аренда', $car->min_days ? 'от '.$car->min_days.' сут.' : null, null, false],
        ['id-card', 'Возраст / стаж', $car->min_age ? 'от '.$car->min_age.' / '.($car->min_experience ?: 0).' лет' : null, null, false],
        ['route', 'Пробег в сутки', $car->daily_km ? $car->daily_km.' км' : null, 'суммируется за все дни', false],
    ], fn ($row) => filled($row[2])));
    $hasTypical = collect($specs)->contains(fn ($row) => $row[4]);

    $seoBlock = \App\Support\CarSeoLinks::build($car);

    $shelves = [
        ['title' => 'В том же классе', 'items' => $sameClass],
    ];
@endphp
@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
    'jsonld' => $jsonld ?? [],
    'ogType' => 'product',
    // Для превью в мессенджерах — jpg/png: webp понимают не все
    'ogImage' => ($og = $car->coverUrl('large', false)) ? url($og) : null,
])

@section('body_class', 'has-sticky-bar')

@section('inline_form_errors', '1')

@section('content')
<section class="page-head">
    <div class="container-x">
        <a class="back-results" href="{{ route('catalog') }}" data-back-results hidden>@include('partials.icon', ['name' => 'chevron-left', 'size' => 16]) {{ \App\Models\Setting::get('back_results_label') ?: 'Назад к результатам' }}</a>
        @include('partials.breadcrumbs')
    </div>
</section>

<section class="section" style="padding-top:8px">
    <div class="container-x car-layout">
        <div class="car-head min-w-0">
            @php($gallery = $car->galleryImages())
            <div class="car-gallery-wrap" data-gallery>
                <div class="card car-gallery" data-skeleton>
                    @if($gallery)
                        <button type="button" class="car-gallery-open" data-gallery-open="0" aria-label="Открыть фото на весь экран">
                            <img src="{{ $gallery[0]['src'] }}" alt="{{ $gallery[0]['alt'] }}" width="1280" height="705" data-gallery-main
                                 @if($gallery[0]['srcset']) srcset="{{ $gallery[0]['srcset'] }}" sizes="(min-width: 1024px) 760px, 100vw" @endif
                                 fetchpriority="high" decoding="async">
                            <span class="car-gallery-zoom" aria-hidden="true">@include('partials.icon', ['name' => 'expand', 'size' => 18])</span>
                        </button>
                    @else
                        <span class="text-sea-100">@include('partials.icon', ['name' => 'car', 'size' => 96, 'stroke' => 1.2])</span>
                    @endif
                </div>
                @if(count($gallery) > 1)
                    <div class="car-thumbs" role="list" data-rail>
                        @foreach($gallery as $i => $image)
                            <button type="button" role="listitem" class="car-thumb @if($i === 0) is-active @endif" data-gallery-thumb="{{ $i }}" aria-label="Фото {{ $i + 1 }} из {{ count($gallery) }}">
                                <img src="{{ $image['thumb'] }}" alt="" width="120" height="66" loading="lazy" decoding="async">
                            </button>
                        @endforeach
                    </div>
                @endif
                <script type="application/json" data-viewed-car>{!! json_encode(['id' => $car->id, 'name' => $car->displayName(), 'url' => route('car.show', $car->slug, false), 'thumb' => $car->coverUrl('card'), 'price' => $from ?: null], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
                <script type="application/json" data-gallery-items>@json(array_map(fn ($i) => ['src' => $i['full'], 'alt' => $i['alt']], $gallery))</script>
            </div>

            <p class="photo-note">@include('partials.icon', ['name' => 'check', 'size' => 13, 'stroke' => 2.4]) {{ \App\Models\Setting::get('photo_note') ?: 'Фото — пример модели. Цвет и год конкретной машины подтвердим в заявке.' }}</p>
            <div class="car-title-row">
                {{-- SEO-текст H1 не меняем, но визуально выделяем название машины: «Аренда» — надпись сверху, «в Крыму» — приглушённо --}}
                @php($pos = mb_strpos($h1, $car->seoName()))
                @php($h1Pre = $pos === false ? '' : trim(mb_substr($h1, 0, $pos)))
                @php($h1Post = $pos === false ? '' : trim(mb_substr($h1, $pos + mb_strlen($car->seoName()))))
                <h1 class="car-h1">
                    @if($pos === false){{ $h1 }}@else
                        @if($h1Pre !== '')<span class="car-h1-pre">{{ $h1Pre }}</span> @endif<span class="car-h1-name">{{ $car->seoName() }}</span>@if($h1Post !== '') <span class="car-h1-post">{{ $h1Post }}</span>@endif
                    @endif
                </h1>
                <div class="car-title-actions">
                    <button type="button" class="fav-toggle fav-toggle-inline" data-fav-toggle="{{ $car->id }}" aria-pressed="false" aria-label="В избранное" title="В избранное">@include('partials.icon', ['name' => 'heart', 'size' => 18])</button>
                    <button type="button" class="fav-toggle fav-toggle-inline" data-share data-share-title="{{ $car->displayName() }} — аренда в Крыму" aria-label="Поделиться" title="Поделиться">@include('partials.icon', ['name' => 'share', 'size' => 18])</button>
                    <button type="button" class="compare-toggle compare-toggle-inline" data-compare-toggle="{{ $car->id }}" data-compare-name="{{ $car->displayName() }}" aria-pressed="false">
                        @include('partials.icon', ['name' => 'compare', 'size' => 16])<span class="compare-toggle-label">Сравнить</span>
                    </button>
                </div>
            </div>

            @if($car->features->isNotEmpty() || ($car->classes->first()))
                <div class="flex flex-wrap gap-1.5">
                    @foreach($car->classes as $class)
                        <a class="chip" style="background:var(--color-sea-50);color:var(--color-sea)" href="{{ route('klass', $class->slug) }}">{{ $class->name }}</a>
                    @endforeach
                    @foreach($car->features as $feature)
                        <span class="chip">@include('partials.icon', ['name' => 'check', 'size' => 12, 'stroke' => 2.4]) {{ $feature->name }}</span>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="car-intro min-w-0">

            @if($specs)
                <h2 class="sr-only">Характеристики</h2>
                <dl class="spec-grid" style="margin-top:20px">
                    @foreach($specs as [$icon, $label, $value, $hint, $typical])
                        <div>
                            <dt>@include('partials.icon', ['name' => $icon, 'size' => 16]) {{ $label }}</dt>
                            <dd>@if($typical)<span class="spec-typical" title="Типично для модели">≈</span>@endif{{ $value }}@if($hint)<span class="spec-hint">{{ $hint }}</span>@endif</dd>
                        </div>
                    @endforeach
                </dl>
                @if($hasTypical)
                    <p class="note" style="margin:8px 0 0">{{ \App\Models\Setting::get('specs_typical_note') ?: '≈ — паспортные данные модели. Комплектация конкретной машины может отличаться — уточним в заявке.' }}</p>
                @endif
            @endif

            @include('partials.trip-fuel')

            @include('partials.car-alternatives')

            @include('partials.included', ['car' => $car])

        </div>

        <div class="car-details min-w-0">
            @if($car->description)
                <h2 style="margin:0 0 12px;font-size:1.375rem">Об автомобиле</h2>
                <div class="prose-body">
                    {{-- Описание собирается из справочника моделей (cars:describe) или пишется в админке --}}
                    @if(str_contains($car->description, '<p'))
                        {!! $car->description !!}
                    @else
                        <p>{{ $car->description }}</p>
                    @endif
                </div>
            @endif

            @if($carReviews->isNotEmpty())
            <div class="car-reviews" id="otzyvy">
                <div class="section-head" style="margin:40px 0 16px">
                    <h2 style="margin:0">Отзывы <span class="note" style="font-size:1rem;font-weight:500">· {{ $carReviews->count() }}</span></h2>
                    <a class="link-arrow" href="{{ route('reviews', ['car' => $car->slug]) }}#review-form">Оставить отзыв @include('partials.icon', ['name' => 'arrow-right', 'size' => 18])</a>
                </div>
                <div class="reviews-list">
                    @foreach($carReviews as $review)
                        @include('partials.review-card', ['review' => $review, 'showCar' => false])
                    @endforeach
                </div>
            </div>
            @endif

            @if(isset($faqs) && $faqs->isNotEmpty())
                <h2 style="margin:40px 0 16px">Вопросы об аренде</h2>
                @include('partials.faq-list')
            @endif
        </div>

        <aside class="card quote-card" aria-labelledby="h-quote">
            <h2 id="h-quote" class="sr-only">Оставить заявку</h2>
            @php($startDefault = now()->addDay()->setTime(10, 0))
            @php($endDefault = $startDefault->copy()->addDays(max(3, (int) $car->min_days)))
            @php($defaultPlace = $locations->firstWhere('is_default_pickup', true) ?? $locations->first())

            <div class="quote-price">
                <div>
                    <span class="quote-price-total" data-quote-total>{{ $from ? 'от '.$fmt($from).' ₽' : 'по запросу' }}</span>
                    <span class="quote-price-days" data-quote-days></span>
                </div>
                @if($from)<span class="quote-price-day" data-quote-per-day>{{ $fmt($from) }} ₽/сут</span>@endif
                <p class="quote-line" data-quote-line aria-live="polite" hidden data-tooltip="{{ Setting::get('deposit_explain') ?: 'Доставка уже в сумме. Залог вносится при получении машины и полностью возвращается после сдачи' }}"></p>
            </div>
            <p class="qs-avail" data-quote-avail hidden></p>

            <form method="post" action="{{ route('booking.store') }}" class="quote-form"
                  data-quote-form
                  data-analytics-car="{{ json_encode(['id' => (string) $car->id, 'name' => $car->displayName(), 'price' => $car->currentPriceFrom(), 'brand' => $car->brand?->name, 'category' => $car->classes->first()?->name], JSON_UNESCAPED_UNICODE) }}"
                  data-quote-url="{{ route('quote') }}"
                  data-car-name="{{ $car->displayName() }}"
                  data-free-text="{{ Setting::get('availability_free_text') ?: 'Свободна на ваши даты' }}"
                  data-busy-text="{{ Setting::get('availability_busy_text') ?: 'На эти даты машина занята. Оставьте заявку — предложим такую же или похожую по той же цене.' }}"
                  @if(($waiver = $extrasList->first(fn ($e) => $e->waives_deposit && ! $e->is_free && $e->price_per_day > 0)))
                  data-deposit-hint="{{ str_replace('{price}', $fmt($waiver->price_per_day), Setting::get('deposit_waiver_hint') ?: 'можно без залога: +{price} ₽/сут') }}"
                  @endif
                  data-wa-base="{{ $wa }}">
                @csrf
                <input type="hidden" name="car_id" value="{{ $car->id }}">
                @include('partials.places-data')

                <div class="daterange-fields" data-daterange data-times="popover" data-min-days="{{ max(1, (int) $car->min_days) }}">
                    <div>
                        <label class="field-label" for="q-start">Начало</label>
                        <input class="field" id="q-start" type="datetime-local" name="starts_at" value="{{ old('starts_at', $startDefault->format('Y-m-d\TH:i')) }}" min="{{ now()->format('Y-m-d\T00:00') }}" required data-quote-input>
                    </div>
                    <div>
                        <label class="field-label" for="q-end">Окончание</label>
                        <input class="field" id="q-end" type="datetime-local" name="ends_at" value="{{ old('ends_at', $endDefault->format('Y-m-d\TH:i')) }}" min="{{ now()->format('Y-m-d\T00:00') }}" required data-quote-input>
                    </div>
                </div>
                @if((int) $car->min_days > 1)
                    <p class="field-hint" data-min-days-hint hidden>@include('partials.icon', ['name' => 'clock', 'size' => 13]) Эту машину сдаём от {{ $car->min_days }} {{ trans_choice('сутки|суток|суток', (int) $car->min_days) }}</p>
                @endif
                <p class="field-hint field-hint-night" data-night-hint hidden>@include('partials.icon', ['name' => 'clock', 'size' => 13]) Выдача или возврат с 21:00 до 8:00 — ночной тариф доставки уже учтён</p>

                <div data-other-return-scope>
                    <label class="field-label" for="q-pickup">{{ Setting::get('hero_place_label') ?: 'Где забрать' }}</label>
                    <select class="field" id="q-pickup" name="pickup_location_id" required data-quote-input data-place-picker data-remember-place="from" data-pickup>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected($defaultPlace && $location->is($defaultPlace))>{{ $location->name }}</option>
                        @endforeach
                    </select>
                    <label class="quote-other"><input type="checkbox" data-other-return> {{ Setting::get('hero_other_return') ?: 'Вернуть в другом месте' }}</label>
                    <div data-return-place hidden>
                        <label class="field-label" for="q-return">Где вернуть</label>
                        <select class="field" id="q-return" name="return_location_id" disabled data-quote-input data-place-picker data-remember-place="to">
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}" @selected($defaultPlace && $location->is($defaultPlace))>{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="field-label" for="q-phone">Телефон</label>
                    <input class="field" id="q-phone" type="tel" name="phone" value="{{ old('phone') }}" required placeholder="+7 (___) ___-__-__" autocomplete="tel" inputmode="tel"
                           data-phone-mask data-ok-text="{{ Setting::get('phone_ok_text') ?: 'Перезвоним на этот номер' }}" aria-describedby="q-phone-error" @error('phone') aria-invalid="true" @enderror>
                    <p class="field-error" id="q-phone-error" data-field-error>@error('phone'){{ $message }}@enderror</p>
                    <p class="field-next" data-phone-next hidden>{{ Setting::get('phone_next_hint') ?: 'Остался один шаг — телефон. Перезвоним и подтвердим наличие' }}</p>
                </div>
                @include('partials.pd-consent', ['id' => 'q-consent'])
                @if($errors->any() && ! $errors->has('phone') && ! $errors->has('pd_consent'))
                    <p class="field-error" role="alert">{{ $errors->first() }}</p>
                @endif
                <button class="btn btn-accent btn-lg btn-block" type="submit" data-submit data-busy-text="Отправляем…">Оставить заявку</button>
                <p class="submit-note">@include('partials.icon', ['name' => 'shield', 'size' => 14]) {{ Setting::get('submit_note') ?: 'Без предоплаты · перезвоним за 15 минут' }}</p>
            </form>

            <p class="quote-error" data-quote-error role="alert"></p>
            <details class="quote-more">
                <summary>Подробный расчёт</summary>
                <div class="quote-summary" data-quote-summary aria-live="polite">Выберите даты — посчитаем аренду и доставку.</div>
            </details>

            <div class="quote-messengers print-hide">
                <span class="note">или напишите:</span>
                @include('partials.messengers', ['variant' => 'icons', 'withVk' => true, 'text' => "Здравствуйте! Хочу {$car->displayName()}. Свободно?"])
            </div>
        </aside>
    </div>
</section>

<div class="container-x">@include('partials.memory-blocks', ['exclude' => $car->id, 'id' => 'car'])</div>

@foreach($shelves as $shelf)
    @if($shelf['items']->isNotEmpty())
        <section class="section section-tight" aria-label="{{ $shelf['title'] }}">
            <div class="container-x">
                <div class="section-head" data-reveal><h2>{{ $shelf['title'] }}</h2></div>
                <div class="shelf" data-rail>
                    @foreach($shelf['items'] as $item)
                        @include('partials.car-card', ['car' => $item])
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endforeach

@if($seoBlock['html'] || $car->seo_text)
    <section class="section section-tight" aria-labelledby="h-car-seo">
        <div class="container-x">
            <div class="card card-pad car-seo-block">
                <h2 id="h-car-seo">{{ $seoBlock['heading'] }}</h2>
                <div class="prose-body">
                    {!! $seoBlock['html'] !!}
                    @if($car->seo_text)
                        {!! str_contains($car->seo_text, '<') ? $car->seo_text : '<p>'.e($car->seo_text).'</p>' !!}
                    @endif
                </div>
            </div>
        </div>
    </section>
@endif
@endsection

@push('sticky')
<div class="sticky-bar">
    <div class="flex items-center gap-2">
        <div class="price min-w-0 flex-1" style="font-size:1.125rem">
            <span class="price-from">Итого</span>
            <span data-quote-total>{{ $from ? 'от '.$fmt($from).' ₽' : 'по запросу' }}</span>
        </div>
        <button type="button" class="icon-btn" data-fab-open aria-label="{{ \App\Models\Setting::get('fab_label', 'Написать нам') }}" aria-expanded="false">@include('partials.icon', ['name' => 'chat', 'size' => 20])</button>
        @if($phoneRaw)
            <a class="icon-btn" href="tel:{{ $phoneRaw }}" aria-label="Позвонить">@include('partials.icon', ['name' => 'phone', 'size' => 20])</a>
        @endif
        <a class="btn btn-accent" href="#h-quote" data-quote-jump>Заявка</a>
    </div>
</div>
@endpush
