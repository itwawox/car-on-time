{{-- Логотипы: WhatsApp, Telegram, VK — Simple Icons (CC0); MAX — официальный знак VK (public domain). Пути — в спрайте App\Support\Icons --}}
@if(($name ?? '') === 'phone')
    @include('partials.icon', ['name' => 'phone', 'size' => $size ?? 20])
@else
    {!! \App\Support\Icons::brand($name, $size ?? 20) !!}
@endif
