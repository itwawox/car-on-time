@php
    use App\Models\Setting;

    $s = fn (string $key, $default = null) => Setting::get($key, $default);
    $mapUrl = fn (string $text) => 'https://yandex.ru/maps/?text='.rawurlencode($text);
    $messengers = array_filter([
        'WhatsApp' => $s('whatsapp'),
        'Telegram' => $s('telegram'),
        'MAX' => $s('max'),
        'ВКонтакте' => $s('vk'),
    ]);
@endphp
<div class="grid gap-4 md:grid-cols-2" style="margin-bottom:24px">
    <div class="card card-pad">
        <p class="field-label" style="margin:0 0 6px">Телефон</p>
        <a class="price" style="font-size:1.5rem" href="tel:{{ $s('phone_raw') }}">{{ $s('phone') }}</a>
        <p class="note" style="margin:6px 0 16px">{{ $s('hours') }}</p>

        <div class="flex flex-wrap items-center gap-2">
            @include('partials.messengers')
            @if($vk = $s('vk'))<a class="messenger messenger-vk" href="{{ $vk }}" target="_blank" rel="noopener">@include('partials.messenger-icon', ['name' => 'vk', 'size' => 18])<span>ВКонтакте</span></a>@endif
        </div>

        @if($email = $s('email'))
            <p style="margin:20px 0 0">
                <span class="field-label" style="display:block">Почта</span>
                <a class="link-arrow" href="mailto:{{ $email }}">{{ $email }}</a>
            </p>
        @endif
    </div>

    <div class="card card-pad">
        @if($pickup = $s('pickup_point'))
            <p class="field-label" style="margin:0 0 6px">Выдача автомобилей</p>
            <p style="margin:0 0 4px;font-weight:600">{{ $pickup }}</p>
            <p class="note" style="margin:0 0 16px">Или привезём машину по адресу в любой город Крыма.</p>
        @endif

        @if($address = $s('address'))
            <p class="field-label" style="margin:0 0 6px">Адрес</p>
            <p style="margin:0 0 6px">{{ $address }}</p>
            <a class="link-arrow" href="{{ $mapUrl($address) }}" target="_blank" rel="noopener">Открыть на Яндекс Картах @include('partials.icon', ['name' => 'arrow-right', 'size' => 16])</a>
        @endif
    </div>
</div>

@if($s('legal_name') || $s('inn'))
    <div class="card card-pad" style="margin-bottom:24px">
        <p class="field-label" style="margin:0 0 10px">Реквизиты</p>
        <dl class="spec-grid" style="margin:0;grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
            @if($legal = $s('legal_name'))<div><dt>Организация</dt><dd>{{ $legal }}</dd></div>@endif
            @if($inn = $s('inn'))<div><dt>ИНН</dt><dd>{{ $inn }}</dd></div>@endif
            @if($ogrn = $s('ogrn'))<div><dt>ОГРН</dt><dd>{{ $ogrn }}</dd></div>@endif
            @if($year = $s('founding_year'))<div><dt>Работаем</dt><dd>с {{ $year }} года</dd></div>@endif
        </dl>
    </div>
@endif
