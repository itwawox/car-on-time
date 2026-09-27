{{-- Иконка из спрайта (App\Support\Icons): пути описаны один раз в конце страницы --}}
<svg width="{{ $size ?? 20 }}" height="{{ $size ?? 20 }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $stroke ?? 1.8 }}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">{!! \App\Support\Icons::use($name) !!}</svg>
