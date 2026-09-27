{{-- «Для какой поездки?» — сценарии с фото настоящих машин, числом и ценой «от» --}}
@php($fmt = fn ($n) => number_format((int) $n, 0, ',', ' '))
@if(!empty($scenarios))
<section class="section trips-section" aria-labelledby="h-trips">
    <div class="container-x">
        <div class="section-head" data-reveal>
            <div>
                <h2 id="h-trips">{{ \App\Models\Setting::get('home_trips_title') ?: 'Для какой поездки?' }}</h2>
                <p>{{ \App\Models\Setting::get('home_trips_lead') ?: 'Выберите задачу — покажем подходящие машины из нашего автопарка.' }}</p>
            </div>
            <a class="link-arrow" href="{{ route('catalog') }}">Весь каталог @include('partials.icon', ['name' => 'arrow-right', 'size' => 18])</a>
        </div>
        <div class="trip-grid">
            @foreach($scenarios as $s)
                <a class="card card-link trip-card" href="{{ $s['url'] }}" data-reveal style="--reveal-delay: {{ $loop->index * 60 }}ms">
                    <span class="trip-media" data-skeleton>
                        @if($s['thumb'])<img src="{{ $s['thumb'] }}" alt="" width="640" height="353" loading="lazy" decoding="async">@endif
                    </span>
                    <span class="trip-body">
                        <span class="trip-title"><span class="trip-icon">@include('partials.icon', ['name' => $s['icon'], 'size' => 18])</span>{{ $s['title'] }}</span>
                        <span class="trip-text">{{ $s['text'] }}</span>
                        <span class="trip-foot">
                            <span>{{ $s['count'] }} {{ trans_choice('машина|машины|машин', $s['count']) }}@if($s['price']) · от <b>{{ $fmt($s['price']) }} ₽</b>@endif</span>
                            <span class="trip-go" aria-hidden="true">@include('partials.icon', ['name' => 'arrow-right', 'size' => 18])</span>
                        </span>
                    </span>
                </a>
            @endforeach
        </div>
        <nav class="body-links" aria-label="По типу кузова">
            <span>По кузову:</span>
            @foreach($bodies as $body)
                <a href="{{ route('kuzov', $body->slug) }}">{{ $body->name }}</a>
            @endforeach
            <span>·</span>
            @foreach($classes as $class)
                <a href="{{ route('klass', $class->slug) }}">{{ $class->name }}</a>
            @endforeach
        </nav>
    </div>
</section>
@endif
