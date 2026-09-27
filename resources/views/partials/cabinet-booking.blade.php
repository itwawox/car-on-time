@php
    $stage = $b->publicStage();
    $fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
@endphp
<article class="card card-pad cabinet-booking">
    <div class="cabinet-booking-head">
        <div>
            <p class="eyebrow" style="margin:0">Заявка №{{ $b->id }}</p>
            <h3>{{ $b->car?->displayName() ?? 'Автомобиль' }}</h3>
        </div>
        <span @class(['badge', 'badge-ok' => in_array($stage, ['confirmed', 'active'], true), 'badge-muted' => in_array($stage, ['done', 'declined'], true)])>{{ \App\Support\BookingStages::title($stage) }}</span>
    </div>
    <dl class="cabinet-facts">
        <div><dt>Даты</dt><dd>{{ $b->starts_at?->translatedFormat('j M, H:i') }} — {{ $b->ends_at?->translatedFormat('j M, H:i') }}</dd></div>
        @if($b->pickupLocation)<div><dt>Выдача</dt><dd>{{ $b->pickupLocation->name }}</dd></div>@endif
        <div><dt>Сумма</dt><dd>{{ $fmt($b->total) }} ₽@if($b->prepaid_amount) · оплачено {{ $fmt($b->prepaid_amount) }} ₽@endif</dd></div>
    </dl>

    @if(session('extended_'.$b->id))
        <p class="details-saved" role="status">{{ session('extended_'.$b->id) }}</p>
    @endif

    <div class="cabinet-actions">
        <a class="btn btn-secondary btn-sm" href="{{ $b->statusUrl() }}">Подробнее</a>
        @if($b->car && $b->car->status === 'published')
            <a class="btn btn-outline btn-sm" href="{{ route('car.show', $b->car->slug) }}">Взять снова</a>
        @endif
    </div>

    @if($canExtend)
        <details class="cabinet-extend" @if($errors->has('ends_at_'.$b->id)) open @endif>
            <summary>Продлить аренду</summary>
            <form method="post" action="{{ route('cabinet.extend', $b) }}" class="cabinet-extend-form">
                @csrf
                <label class="field-label" for="ext-{{ $b->id }}">Вернуть машину</label>
                <input class="field" id="ext-{{ $b->id }}" type="datetime-local" name="ends_at" required
                       min="{{ $b->ends_at->copy()->addHour()->format('Y-m-d\TH:i') }}" value="{{ $b->ends_at->copy()->addDay()->format('Y-m-d\TH:i') }}">
                <button class="btn btn-primary btn-sm" type="submit" data-submit data-busy-text="Считаем…">Запросить продление</button>
                <p class="field-error">@error('ends_at_'.$b->id){{ $message }}@enderror @error('ends_at'){{ $message }}@enderror</p>
                <p class="note">Посчитаем доплату и проверим, свободна ли машина. Продление подтвердит менеджер.</p>
            </form>
        </details>
    @endif
</article>
