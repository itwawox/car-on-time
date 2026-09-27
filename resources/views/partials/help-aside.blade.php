@php
    use App\Models\Setting;
    $phone = Setting::get('phone');
    $phoneRaw = Setting::get('phone_raw');
    $whatsapp = Setting::get('whatsapp');
    $telegram = Setting::get('telegram');
@endphp
{{-- Боковая колонка на текстовых страницах: помощь с выбором. Тексты — «Настройки сайта». --}}
<aside class="page-aside" aria-labelledby="h-help">
    @if(!empty($toc) && count($toc) >= 3)
        <nav class="card card-pad article-toc" aria-label="Содержание" style="margin-bottom:12px">
            <p class="field-label" style="margin:0 0 8px">Содержание</p>
            <ol>
                @foreach($toc as $item)
                    <li><a href="#{{ $item['id'] }}">{{ $item['title'] }}</a></li>
                @endforeach
            </ol>
        </nav>
    @endif
    <div class="card card-pad">
        <span class="usp-icon" style="margin-bottom:14px">@include('partials.icon', ['name' => 'phone', 'size' => 22])</span>
        <h2 id="h-help" style="font-size:1.125rem;margin:0 0 6px">{{ Setting::get('help_title', 'Нужна помощь с выбором?') }}</h2>
        <p class="note" style="margin:0 0 16px;font-size:.875rem">{{ Setting::get('help_text', 'Позвоните или напишите — подберём машину под ваши даты и подтвердим наличие за 15 минут.') }}</p>
        @if($phone)
            <a class="btn btn-primary btn-block" href="tel:{{ $phoneRaw }}">{{ $phone }}</a>
        @endif
        <div style="margin-top:8px">@include('partials.messengers', ['variant' => 'grid'])</div>
        <p class="note" style="margin:14px 0 0">{{ Setting::get('hours') }}</p>
    </div>
    <a class="card card-link card-pad flex items-center justify-between gap-3" href="{{ route('catalog') }}" style="margin-top:12px">
        <span>
            <b style="display:block">{{ Setting::get('help_catalog_title', 'Весь каталог') }}</b>
            <span class="note" style="display:block;line-height:1.45;margin-top:2px">{{ Setting::get('help_catalog_text', 'Эконом, кроссоверы, минивэны и бизнес-класс') }}</span>
        </span>
        <span class="text-sea">@include('partials.icon', ['name' => 'arrow-right', 'size' => 20])</span>
    </a>
</aside>
