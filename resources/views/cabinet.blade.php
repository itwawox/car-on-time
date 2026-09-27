@php
    use App\Models\Booking;
    use App\Models\Setting;
    use App\Support\BookingStages;
    $fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
@endphp
@extends('layouts.app', ['title' => 'Мои брони | '.Setting::get('brand_name', 'Car on Time'), 'noindex' => true])

@section('content')
<section class="page-head">
    <div class="container-x">
        <h1>{{ Setting::get('cabinet_title') ?: 'Мои брони' }}</h1>
        @if($stage !== 'list')
            <p class="lead">{{ Setting::get('cabinet_lead') ?: 'Все ваши заявки и брони в одном месте: статус, даты, продление аренды.' }}</p>
        @endif
    </div>
</section>

<section class="section" style="padding-top:8px">
    <div class="container-x">
        @if($stage === 'phone' && ! $smsLogin)
            <div class="card card-pad cabinet-login">
                <p style="margin:0">{{ Setting::get('cabinet_no_sms_text') ?: 'Чтобы открыть свои брони, перейдите по ссылке из сообщения о заявке и нажмите «Все мои брони».' }}</p>
                @if($phoneRaw = Setting::get('phone_raw'))
                    <a class="btn btn-outline" href="tel:{{ $phoneRaw }}">@include('partials.icon', ['name' => 'phone', 'size' => 18]) {{ Setting::get('phone') }}</a>
                @endif
            </div>
        @elseif($stage === 'phone')
            <form class="card card-pad cabinet-login" method="post" action="{{ route('cabinet.code') }}">
                @csrf
                <label class="field-label" for="c-phone">Телефон, на который оформляли заявку</label>
                <input class="field" id="c-phone" type="tel" name="phone" value="{{ old('phone') }}" required placeholder="+7 (___) ___-__-__" autocomplete="tel" inputmode="tel" data-phone-mask aria-describedby="c-phone-error">
                <p class="field-error" id="c-phone-error" data-field-error>@error('phone'){{ $message }}@enderror</p>
                <button class="btn btn-accent btn-lg btn-block" type="submit" data-submit data-busy-text="Отправляем…">Получить код в SMS</button>
                <p class="note">Пароль не нужен — пришлём одноразовый код. Или откройте ссылку из сообщения о заявке и нажмите «Все мои брони».</p>
            </form>
        @elseif($stage === 'code')
            <form class="card card-pad cabinet-login" method="post" action="{{ route('cabinet.verify') }}">
                @csrf
                <input type="hidden" name="phone" value="{{ session('code_sent_to') }}">
                <label class="field-label" for="c-code">Код из SMS на +{{ session('code_sent_to') }}</label>
                <input class="field cabinet-code" id="c-code" type="text" name="code" required inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="••••••" aria-describedby="c-code-error">
                <p class="field-error" id="c-code-error">@error('code'){{ $message }}@enderror</p>
                <button class="btn btn-accent btn-lg btn-block" type="submit" data-submit data-busy-text="Проверяем…">Войти</button>
                <p class="note">Код действует {{ 10 }} минут. <a href="{{ route('cabinet') }}">Другой номер или новый код</a></p>
            </form>
        @else
            <div class="cabinet-head">
                <p class="note" style="margin:0">Вы вошли по номеру +{{ \App\Support\CustomerSession::phone() }}</p>
                <form method="post" action="{{ route('cabinet.logout') }}">@csrf<button class="btn btn-outline btn-sm" type="submit">Выйти</button></form>
            </div>

            @if($upcoming->isEmpty() && $past->isEmpty())
                <div class="card card-pad">
                    <p style="margin:0 0 12px">По этому номеру пока нет заявок.</p>
                    <a class="btn btn-accent" href="{{ route('catalog') }}">Выбрать машину</a>
                </div>
            @endif

            @if($upcoming->isNotEmpty())
                <h2 class="cabinet-h2">Текущие и предстоящие</h2>
                <div class="cabinet-list">
                    @foreach($upcoming as $b)
                        @include('partials.cabinet-booking', ['b' => $b, 'canExtend' => $b->status === 'confirmed'])
                    @endforeach
                </div>
            @endif

            @if($past->isNotEmpty())
                <h2 class="cabinet-h2">Прошлые</h2>
                <div class="cabinet-list">
                    @foreach($past as $b)
                        @include('partials.cabinet-booking', ['b' => $b, 'canExtend' => false])
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</section>
@endsection
