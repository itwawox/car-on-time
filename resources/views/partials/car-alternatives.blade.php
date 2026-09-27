{{-- «Такая же, но…» — $alternatives из CarAlternatives::for() --}}
@if(!empty($alternatives))
    @php($fmt = fn ($n) => number_format((int) $n, 0, ',', ' '))
    <section class="alts" aria-labelledby="h-alts">
        <div class="alts-head">
            <h2 id="h-alts">{{ \App\Models\Setting::get('alt_block_title') ?: 'Сравните с похожими' }}</h2>
            <p class="note">{{ \App\Models\Setting::get('alt_block_text') ?: 'Подобрали по кузову, классу и цене. Цифры — разница с этой машиной.' }}</p>
        </div>
        <ul class="alts-list">
            @foreach($alternatives as $i => $alt)
                @php($c = $alt['car'])
                <li class="alt @if($i >= 4) alt-extra @endif">
                    <a class="alt-media" href="{{ $c['url'] }}" tabindex="-1" aria-hidden="true" data-skeleton>
                        @if($c['thumb'])<img src="{{ $c['thumb'] }}" alt="" width="160" height="88" loading="lazy" decoding="async">@endif
                    </a>
                    <div class="alt-body">
                        <p class="alt-kicker">{{ $alt['title'] }}</p>
                        <a class="alt-name" href="{{ $c['url'] }}">{{ $c['name'] }}</a>
                        <div class="alt-deltas">
                            @foreach($alt['deltas'] as $d)
                                <span class="delta {{ $d['good'] ? 'delta-good' : 'delta-cost' }}">{{ $d['text'] }}</span>
                            @endforeach
                        </div>
                    </div>
                    <div class="alt-side">
                        <span class="alt-price">{{ $fmt($c['price']) }} ₽<small>/сут</small></span>
                        <button type="button" class="compare-toggle" data-compare-toggle="{{ $c['id'] }}" data-compare-name="{{ $c['name'] }}" aria-pressed="false" title="Сравнить">
                            @include('partials.icon', ['name' => 'compare', 'size' => 14])<span class="compare-toggle-label">Сравнить</span>
                        </button>
                    </div>
                </li>
            @endforeach
        </ul>
        @if(count($alternatives) > 4)
            <button type="button" class="link-arrow alts-more" onclick="this.closest('.alts').classList.add('is-open');this.remove()">Показать ещё {{ count($alternatives) - 4 }}</button>
        @endif
    </section>
@endif
