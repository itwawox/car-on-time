@php use App\Models\Setting; @endphp
{{-- Плавающая кнопка «Написать»: мессенджеры и телефон в одно касание. Работает и без JS (<details>). --}}
<details class="contact-fab" data-fab>
    <summary class="contact-fab-toggle" aria-label="{{ Setting::get('fab_label', 'Написать нам') }}">
        <span class="contact-fab-icon contact-fab-icon-open">@include('partials.icon', ['name' => 'chat', 'size' => 24, 'stroke' => 2])</span>
        <span class="contact-fab-icon contact-fab-icon-close">@include('partials.icon', ['name' => 'close', 'size' => 22, 'stroke' => 2])</span>
        <span class="contact-fab-label">{{ Setting::get('fab_label', 'Написать нам') }}</span>
    </summary>
    <div class="contact-fab-menu">
        <p class="contact-fab-title">{{ Setting::get('fab_title', 'Ответим за пару минут') }}</p>
        @include('partials.messengers', ['variant' => 'list'])
        <form class="fab-callback" method="post" action="{{ route('lead.store') }}" data-callback-form>
            @csrf
            <input type="hidden" name="type" value="callback">
            <label class="fab-callback-label" for="cb-phone">{{ Setting::get('callback_label') ?: 'Или перезвоним вам' }}</label>
            <div class="fab-callback-row">
                <input class="field" id="cb-phone" type="tel" name="phone" required placeholder="+7 (___) ___-__-__" autocomplete="tel" inputmode="tel" data-phone-mask aria-describedby="cb-error">
                <button class="btn btn-primary" type="submit" data-submit data-busy-text="…" aria-label="Перезвоните мне">@include('partials.icon', ['name' => 'phone', 'size' => 18])</button>
            </div>
            <div class="hp" aria-hidden="true"><label>Сайт <input name="website" tabindex="-1" autocomplete="off"></label></div>
            @include('partials.pd-consent', ['id' => 'cb-consent'])
            <p class="field-error" id="cb-error" data-callback-msg></p>
        </form>
        @if($phoneRaw = Setting::get('phone_raw'))
            <a class="messenger messenger-phone" href="tel:{{ $phoneRaw }}">
                @include('partials.messenger-icon', ['name' => 'phone', 'size' => 18])
                <span>{{ Setting::get('phone') }}</span>
            </a>
        @endif
    </div>
</details>
