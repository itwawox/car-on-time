{{-- «Часто ищут» под формой: готовые запросы в один клик (список — «Настройки сайта → Главная») --}}
@if(!empty($hints))
    <nav class="hero-hints" aria-label="Часто ищут">
        <span class="hero-hints-label">{{ \App\Models\Setting::get('hero_hints_label') ?: 'Часто ищут:' }}</span>
        @foreach($hints as $hint)
            <a class="hero-hint" href="{{ $hint['url'] }}">{{ $hint['label'] }}</a>
        @endforeach
    </nav>
@endif
