{{-- «Сцена» первого экрана: машина под выбранную задачу. Вкладки меняют машину только по нажатию. Машины и фото — «Настройки сайта → Главная: сцена». --}}
@if(!empty($stage))
    @php($fmt = fn ($n) => number_format((int) $n, 0, ',', ' '))
    @php($first = $stage[0])
    <div class="hero-stage" data-hero-stage>
        <div class="hero-stage-tabs" role="group" aria-label="{{ \App\Models\Setting::get('hero_stage_label') ?: 'Для какой поездки' }}">
            @foreach($stage as $i => $slide)
                <button type="button" aria-pressed="{{ $i === 0 ? 'true' : 'false' }}" aria-controls="hero-stage-scene"
                        data-stage-slide="{{ json_encode([
                            'title' => $slide['title'],
                            'meta' => $slide['count'].' '.trans_choice('машина|машины|машин', $slide['count']).($slide['price'] ? ' · от '.$fmt($slide['price']).' ₽' : ''),
                            'url' => $slide['url'], 'image' => $slide['image'], 'alt' => $slide['alt'], 'cutout' => $slide['cutout'],
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}">{{ $slide['tab'] }}</button>
            @endforeach
        </div>
        <a class="hero-stage-scene" id="hero-stage-scene" href="{{ $first['url'] }}" data-stage-link data-skeleton aria-live="polite" @if($first['lqip']) style="{{ \App\Support\Lqip::style($first['lqip']) }}" @endif>
            <img @class(['is-cutout' => $first['cutout']]) src="{{ $first['image'] }}" alt="{{ $first['alt'] }}" width="1280" height="704" fetchpriority="high" data-stage-img>
        </a>
        <p class="hero-stage-caption">
            <span><b data-stage-title>{{ $first['title'] }}</b> <span data-stage-meta>{{ $first['count'] }} {{ trans_choice('машина|машины|машин', $first['count']) }}@if($first['price']) · от {{ $fmt($first['price']) }} ₽@endif</span></span>
            <a class="hero-stage-go" href="{{ $first['url'] }}" data-stage-link>{{ \App\Models\Setting::get('hero_stage_link') ?: 'Смотреть' }}</a>
        </p>
    </div>
@endif
