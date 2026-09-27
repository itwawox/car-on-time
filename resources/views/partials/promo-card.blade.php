<article class="card card-link promo-card" data-reveal>
    <a class="promo-card-media" href="{{ $promotion->url() }}" tabindex="-1" aria-hidden="true">
        @if($cover = $promotion->coverUrl('600'))
            <img src="{{ $cover }}" alt="" width="600" height="338" loading="lazy" decoding="async">
        @else
            <span class="promo-card-fallback">@include('partials.icon', ['name' => 'tag', 'size' => 40])</span>
        @endif
        @if($promotion->badge)<span class="promo-badge">{{ $promotion->badge }}</span>@endif
    </a>
    <div class="promo-card-body">
        <h3><a href="{{ $promotion->url() }}">{{ $promotion->title }}</a></h3>
        @if($promotion->excerpt)<p>{{ $promotion->excerpt }}</p>@endif
        <div class="promo-card-foot">
            @if($period = $promotion->periodLabel())<span class="note">{{ $period }}</span>@endif
            @if($promotion->promo_code)<span class="promo-code">{{ $promotion->promo_code }}</span>@endif
        </div>
    </div>
</article>
