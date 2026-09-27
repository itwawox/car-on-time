{{-- Строка доверия прямо в первом экране вместо отдельного блока «Преимущества» (тексты — usp_* в настройках) --}}
@php
    use App\Models\Setting;
    $items = array_filter([
        ($count ?? null) ? $count.' '.trans_choice('машина|машины|машин', $count).' в каталоге' : null,
        Setting::get('usp_1_title', 'Без предоплаты'),
        trim(Setting::get('usp_3_title', 'Кресло 0 ₽').' '.mb_strtolower((string) Setting::get('usp_3_text', 'И дополнительный водитель'))),
        Setting::get('hero_stat_1_value', '15 мин') ? 'Ответ за '.Setting::get('hero_stat_1_value', '15 мин') : null,
        Setting::get('usp_4_text', 'Доставка к рейсу'),
    ]);
@endphp
<div class="trust-strip">
    <ul>
        @foreach($items as $item)
            <li>@include('partials.icon', ['name' => 'check', 'size' => 14, 'stroke' => 2.6]) {{ $item }}</li>
        @endforeach
    </ul>
    <a class="btn btn-on-dark btn-sm" href="{{ route('quiz') }}">Не знаю, что выбрать @include('partials.icon', ['name' => 'arrow-right', 'size' => 16])</a>
</div>
