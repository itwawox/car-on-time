@php
    use App\Models\Setting;
    $fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
    $best = $variants[0] ?? null;
    $others = array_slice($variants, 1);
@endphp
@extends('layouts.app', ['title' => 'Подобрали машину под ваши ответы | '.Setting::get('brand_name', 'Car on Time'), 'noindex' => true])

@section('content')
<span hidden data-analytics-goal="quiz_result"></span>
<section class="page-head">
    <div class="container-x">
        @include('partials.breadcrumbs', ['crumbs' => [
            ['name' => 'Главная', 'url' => route('home')],
            ['name' => 'Подбор', 'url' => route('quiz')],
            ['name' => 'Результат'],
        ]])
        <h1>{{ Setting::get('quiz_result_title') ?: 'Подобрали под ваши ответы' }}</h1>
        <span hidden data-quiz-result data-summary="{{ $summary }}"></span>
        <div class="qr-answers">
            @foreach($steps as $step)
                @foreach($step['options'] as $option)
                    @if(in_array($option['value'], (array) ($answers[$step['name']] ?? []), true) && $option['value'] !== 'any')
                        <span class="qr-chip">@include('partials.icon', ['name' => $option['icon'], 'size' => 14]) {{ $option['label'] }}</span>
                    @endif
                @endforeach
            @endforeach
            <a class="qr-edit" href="{{ $editUrl }}">@include('partials.icon', ['name' => 'chevron-left', 'size' => 14]) Изменить ответы</a>
        </div>
        @if(in_array('budget', $relaxed, true))
            <p class="qr-note">В выбранный бюджет подходящих машин нет — показали ближайшие по цене.</p>
        @elseif(in_array('gearbox', $relaxed, true))
            <p class="qr-note">С выбранной коробкой машин под эти условия нет — показали и другие варианты.</p>
        @endif
    </div>
</section>

<section class="section" style="padding-top:20px">
    <div class="container-x">
        @if($best)
            @php($car = $best['car'])
            <article class="card qr-best hero-anim">
                <a class="qr-best-media" href="{{ route('car.show', $car->slug) }}" data-skeleton>
                    @if($cover = $car->coverUrl('large'))
                        <img src="{{ $cover }}" alt="{{ $car->coverAlt() }}" width="1280" height="705" fetchpriority="high">
                    @endif
                </a>
                <div class="qr-best-body">
                    <div class="qr-best-head">
                        <span class="eyebrow">{{ $best['label'] }}</span>
                        <span class="qr-match" style="--p: {{ $best['row']['match'] }}" aria-label="Совпадение {{ $best['row']['match'] }}%"><b>{{ $best['row']['match'] }}%</b><small>совпадение</small></span>
                    </div>
                    <h2><a href="{{ route('car.show', $car->slug) }}">{{ $car->displayName() }}</a></h2>
                    <ul class="qr-reasons">
                        @foreach($best['row']['reasons'] as $reason)
                            <li>@include('partials.icon', ['name' => 'check', 'size' => 16, 'stroke' => 2.6]) {{ $reason }}</li>
                        @endforeach
                    </ul>
                    <div class="qr-best-foot">
                        <div class="price"><span class="price-from">от</span>{{ $fmt($best['row']['price']) }} ₽ <small>/ сутки</small></div>
                        <div class="qr-actions">
                            <button type="button" class="fav-toggle fav-toggle-inline" data-fav-toggle="{{ $car->id }}" aria-pressed="false" aria-label="В избранное" title="В избранное">@include('partials.icon', ['name' => 'heart', 'size' => 18])</button>
                            <a class="btn btn-accent btn-lg" href="{{ route('car.show', $car->slug) }}#h-quote">Оставить заявку</a>
                        </div>
                    </div>
                </div>
            </article>

            @if($others)
                <div class="qr-others">
                    @foreach($others as $v)
                        <article class="card qr-alt" data-reveal style="--reveal-delay: {{ $loop->index * 100 }}ms">
                            <a class="qr-alt-media" href="{{ route('car.show', $v['car']->slug) }}" tabindex="-1" aria-hidden="true" data-skeleton>
                                @if($c = $v['car']->coverUrl('card'))<img src="{{ $c }}" alt="" width="640" height="353" loading="lazy" decoding="async">@endif
                            </a>
                            <div class="qr-alt-body">
                                <div class="qr-alt-head">
                                    <span class="qr-alt-label">{{ $v['label'] }}</span>
                                    <span class="qr-alt-match">{{ $v['row']['match'] }}%</span>
                                </div>
                                <h3><a href="{{ route('car.show', $v['car']->slug) }}">{{ $v['car']->displayName() }}</a></h3>
                                <ul class="qr-reasons qr-reasons-sm">
                                    @foreach(array_slice($v['row']['reasons'], 0, 2) as $reason)
                                        <li>@include('partials.icon', ['name' => 'check', 'size' => 14, 'stroke' => 2.6]) {{ $reason }}</li>
                                    @endforeach
                                </ul>
                                <div class="qr-alt-foot">
                                    <span class="price"><span class="price-from">от</span>{{ $fmt($v['row']['price']) }} ₽ <small>/ сут</small></span>
                                    @php($diff = $v['row']['price'] - $best['row']['price'])
                                    <span class="delta {{ $diff < 0 ? 'delta-good' : 'delta-cost' }}">{{ $diff < 0 ? '−' : '+' }}{{ $fmt(abs($diff)) }} ₽/сут</span>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif

            <div class="qr-more">
                <p><b>{{ $count }} {{ trans_choice('машина подходит|машины подходят|машин подходят', $count) }}</b> под ваши ответы</p>
                <a class="btn btn-primary" href="{{ $catalogUrl }}">Смотреть все подходящие @include('partials.icon', ['name' => 'arrow-right', 'size' => 18])</a>
                <a class="btn btn-outline" href="{{ $editUrl }}">Изменить ответы</a>
            </div>
        @else
            <div class="card card-pad text-center">
                <p class="m-0 mb-4 font-semibold">Под такие ответы машин не нашлось.</p>
                <a class="btn btn-primary" href="{{ route('catalog') }}">Смотреть весь каталог</a>
                <a class="btn btn-outline" href="{{ $editUrl }}">Изменить ответы</a>
            </div>
        @endif
    </div>
</section>
@endsection
