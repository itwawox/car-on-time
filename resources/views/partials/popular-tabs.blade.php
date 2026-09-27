{{-- «Популярные машины» со вкладками: все наборы отрисованы сервером, JS только переключает (без JS — вкладка «Все») --}}
@if(!empty($popular))
<section class="section section-tight" aria-labelledby="h-hits" data-tabs>
    <div class="container-x">
        <div class="section-head" data-reveal>
            <div>
                <h2 id="h-hits">{{ \App\Models\Setting::get('home_hits_title') ?: 'Популярные машины' }}</h2>
                <p>{{ \App\Models\Setting::get('home_hits_lead', 'Проверенные модели, которые чаще всего бронируют на сезон.') }}</p>
            </div>
        </div>
        <div class="pop-tabs" role="tablist" aria-label="Категории" data-rail>
            @foreach($popular as $tab)
                <button type="button" role="tab" id="pt-{{ $tab['key'] }}" aria-controls="pp-{{ $tab['key'] }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" @unless($loop->first) tabindex="-1" @endunless>
                    {{ $tab['label'] }} <span>{{ $tab['total'] }}</span>
                </button>
            @endforeach
        </div>
        @foreach($popular as $tab)
            <div class="pop-panel" role="tabpanel" id="pp-{{ $tab['key'] }}" aria-labelledby="pt-{{ $tab['key'] }}" @unless($loop->first) hidden @endunless>
                <div class="car-grid pop-grid" data-rail>
                    @foreach($tab['cars'] as $car)
                        @include('partials.car-card', ['car' => $car, 'eager' => false])
                    @endforeach
                </div>
                <p class="pop-more"><a class="btn btn-outline" href="{{ $tab['url'] }}">Смотреть все {{ $tab['total'] }} {{ trans_choice('машину|машины|машин', $tab['total']) }} @include('partials.icon', ['name' => 'arrow-right', 'size' => 18])</a></p>
            </div>
        @endforeach
    </div>
</section>
@endif
