@php
    use App\Models\Setting;
    // Кнопки мессенджеров с логотипами. $text — предзаполненное сообщение (WhatsApp и Telegram его подставят).
    // $withVk — добавить ВКонтакте (иконками в карточке машины и подвале).
    $text = rawurlencode($text ?? Setting::get('messenger_text', 'Здравствуйте! Хочу арендовать авто в Крыму.'));
    $items = array_filter([
        'whatsapp' => ($u = Setting::get('whatsapp')) ? [$u.(str_contains($u, '?') ? '&' : '?').'text='.$text, 'WhatsApp'] : null,
        'telegram' => ($u = Setting::get('telegram')) ? [$u, 'Telegram'] : null,
        'max' => ($u = Setting::get('max')) ? [$u, 'MAX'] : null,
        'vk' => ($withVk ?? false) && ($u = Setting::get('vk')) ? [$u, 'ВКонтакте'] : null,
    ]);
    $variant = is_string($variant ?? null) ? $variant : 'buttons';
@endphp
@if($items)
    <div class="messengers messengers-{{ $variant }}">
        @foreach($items as $key => [$url, $label])
            <a class="messenger messenger-{{ $key }}" href="{{ $url }}" target="_blank" rel="noopener" aria-label="Написать в {{ $label }}" @if($key === 'whatsapp') data-wa-link @endif>
                @include('partials.messenger-icon', ['name' => $key, 'size' => $variant === 'icons' ? 20 : 18])
                @if($variant !== 'icons')<span>{{ $label }}</span>@endif
            </a>
        @endforeach
    </div>
@endif
