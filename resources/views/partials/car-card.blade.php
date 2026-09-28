@php
    $cover = $car->coverUrl('card');
    $from = $car->currentPriceFrom();
    $eager = $eager ?? false;
    $badge = \App\Support\CarBadges::for($car->id);
    // Тег заголовка карточки — только h2 или h3; своё имя, чтобы не подхватить переменные страницы
    $titleTag = in_array($titleTag ?? null, ['h2', 'h3'], true) ? $titleTag : 'h3';
@endphp
<article class="card card-link car-card relative" @isset($reveal) data-reveal @endisset>
    <a class="car-card-media" data-skeleton href="{{ route('car.show', $car->slug) }}" tabindex="-1" aria-hidden="true">
        @if($cover)
            <img src="{{ $cover }}" alt="{{ $car->coverAlt() }}" width="640" height="353" style="view-transition-name: car-{{ $car->id }}"
                 @if($srcset = $car->coverSrcset()) srcset="{{ $srcset }}" sizes="(min-width: 1200px) 290px, (min-width: 900px) 33vw, (min-width: 560px) 50vw, 100vw" @endif
                 @if($eager) fetchpriority="high" @else loading="lazy" @endif decoding="async">
        @else
            <span class="grid h-full place-items-center text-sea-100">@include('partials.icon', ['name' => 'car', 'size' => 56, 'stroke' => 1.4])</span>
        @endif
    </a>
    @if($badge)<span class="car-badge car-badge-{{ $badge['key'] }}" data-tooltip="{{ $badge['hint'] }}">{{ $badge['label'] }}</span>@endif
    <div class="car-card-actions">
        <button type="button" class="fav-toggle" data-fav-toggle="{{ $car->id }}" aria-pressed="false" aria-label="В избранное" title="В избранное">@include('partials.icon', ['name' => 'heart', 'size' => 16])</button>
        <button type="button" class="compare-toggle" data-compare-toggle="{{ $car->id }}" data-compare-name="{{ $car->displayName() }}" aria-pressed="false" aria-label="Сравнить" title="Сравнить">
            @include('partials.icon', ['name' => 'compare', 'size' => 16])<span class="compare-toggle-label">Сравнить</span>
        </button>
    </div>
    <div class="car-card-body">
        <{{ $titleTag }} class="car-card-title"><a href="{{ route('car.show', $car->slug) }}">{{ $car->displayName() }}</a></{{ $titleTag }}>
        <div class="flex flex-wrap gap-1.5">
            <span class="chip">@include('partials.icon', ['name' => 'gearbox', 'size' => 12]) {{ $car->fuel === 'electric' ? 'Электро' : $car->gearboxLabel() }}</span>
            @if($car->seats)<span class="chip">@include('partials.icon', ['name' => 'seats', 'size' => 12]) {{ $car->seats }} {{ trans_choice('место|места|мест', (int) $car->seats) }}</span>@endif
            @if($car->drivetrain === '4wd')<span class="chip">@include('partials.icon', ['name' => 'drive', 'size' => 12]) 4WD</span>@endif
            @if($car->relationLoaded('classes') && $car->classes->first())
                <span class="chip">{{ $car->classes->first()->name }}</span>
            @endif
        </div>
        <div class="car-card-foot">
            <div class="price" data-card-price="{{ $car->id }}">
                @if($from)
                    <span class="price-from">от</span>{{ number_format($from, 0, ',', ' ') }} ₽ <small>/ сутки</small>
                @else
                    <span class="price-from">Цена</span><small style="font-size:1rem;color:var(--color-ink);font-weight:600">по запросу</small>
                @endif
            </div>
            <span class="btn btn-secondary btn-sm relative z-10 pointer-events-none" aria-hidden="true">@include('partials.icon', ['name' => 'arrow-right', 'size' => 18])</span>
        </div>
    </div>
</article>
