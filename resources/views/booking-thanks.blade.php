@php
    use App\Models\Setting;
    $steps = Setting::get('thanks_steps');
    if (! is_array($steps) || ! $steps) {
        $steps = [
            ['title' => 'Перезвоним и подтвердим наличие', 'text' => 'Обычно в течение 15 минут. Если удобнее — напишите в мессенджер.'],
            ['title' => 'Подготовьте документы', 'text' => 'Паспорт и водительское удостоверение. Фото можно прислать заранее — договор будет готов к встрече.'],
            ['title' => 'Встреча и осмотр машины', 'text' => 'Вместе осмотрим машину и зафиксируем её состояние на фото.'],
            ['title' => 'Оплата при получении', 'text' => 'Предоплаты нет. Залог вернём после сдачи машины.'],
        ];
    }
    $msg = 'Здравствуйте! Заявка №'.$booking->id.' на '.($booking->car?->displayName() ?? 'авто').'.';
    $stage = $booking->publicStage();
    $track = \App\Support\BookingStages::TRACK;
    $current = array_search($stage, $track, true);
@endphp
@extends('layouts.app', ['title' => 'Заявка №'.$booking->id.' принята | '.Setting::get('brand_name', 'Car on Time'), 'noindex' => true])

@section('content')
<section class="section">
    <div class="container-x thanks">
        <div class="card card-pad thanks-card hero-anim" data-analytics-purchase="{{ json_encode(['id' => $booking->id, 'revenue' => (int) $booking->total, 'product' => ['id' => (string) $booking->car_id, 'name' => $booking->car?->displayName(), 'price' => (int) $booking->total, 'quantity' => 1]], JSON_UNESCAPED_UNICODE) }}">
            <span class="thanks-icon">@include('partials.icon', ['name' => 'check', 'size' => 32, 'stroke' => 2.4])</span>
            <p class="eyebrow" style="margin:16px auto 0">Заявка №{{ $booking->id }}</p>
            @if($stage === 'received')
                <h1>{{ Setting::get('thanks_title') ?: 'Заявка принята' }}</h1>
                <p class="text-muted" style="margin:0 auto;max-width:48ch">Спасибо@if($booking->customer_name), {{ $booking->customer_name }}@endif! Уточним наличие {{ $booking->car?->displayName() }} и свяжемся по номеру <b class="text-ink">{{ $booking->phone }}</b>.</p>
            @else
                <h1>{{ \App\Support\BookingStages::title($stage) }}</h1>
                <p class="text-muted" style="margin:0 auto;max-width:48ch">{{ \App\Support\BookingStages::text($stage) }}</p>
            @endif

            @if($stage !== 'declined')
                <ol class="stage-track" aria-label="Статус заявки">
                    @foreach($track as $i => $key)
                        <li @class(['is-done' => $i < $current, 'is-current' => $i === $current]) @if($i === $current) aria-current="step" @endif>
                            <span class="stage-dot"></span>
                            <span class="stage-label">{{ \App\Support\BookingStages::title($key) }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif

            @if(session('payment_error'))
                <p class="quote-error" role="alert" style="margin-top:16px">{{ session('payment_error') }}</p>
            @endif
            @if($booking->prepaid_at)
                <div class="prepay prepay-done">
                    <b>{{ Setting::get('prepaid_title') ?: 'Предоплата получена' }} — {{ number_format($booking->prepaid_amount, 0, ',', ' ') }} ₽</b>
                    <p>{{ Setting::get('prepaid_text') ?: 'Машина закреплена за вами. Остаток — при получении.' }}</p>
                </div>
            @elseif(\App\Services\Payments\PrepaymentService::canPrepay($booking))
                <form class="prepay" method="post" action="{{ route('booking.pay', $booking->public_token) }}">
                    @csrf
                    <b>{{ Setting::get('prepay_title') ?: 'Закрепите машину за собой' }}</b>
                    <p>{{ Setting::get('prepay_text') ?: 'Внесите предоплату онлайн — картой МИР, через СБП или другим удобным способом. Остаток — при получении. Можно и без предоплаты: бронь всё равно действует.' }}</p>
                    <button class="btn btn-accent" type="submit">{{ Setting::get('prepay_button') ?: 'Внести предоплату' }} {{ number_format(\App\Services\Payments\PaymentSettings::amountFor($booking), 0, ',', ' ') }} ₽</button>
                </form>
            @endif

            <dl class="thanks-summary">
                <div><dt>Машина</dt><dd>{{ $booking->car?->displayName() }}</dd></div>
                <div><dt>Даты</dt><dd>{{ $booking->starts_at?->translatedFormat('j M, H:i') }} — {{ $booking->ends_at?->translatedFormat('j M, H:i') }}</dd></div>
                @if($booking->pickupLocation)<div><dt>Выдача</dt><dd>{{ $booking->pickupLocation->name }}</dd></div>@endif
                @if($booking->extras)<div><dt>Доп. услуги</dt><dd>{{ implode(', ', array_column($booking->extras, 'name')) }}</dd></div>@endif
                @if(isset($booking->quote_snapshot['deposit']))<div><dt>Залог</dt><dd>{{ ($booking->quote_snapshot['deposit_waived'] ?? false) ? 'без залога' : number_format((int) $booking->quote_snapshot['deposit'], 0, ',', ' ').' ₽ — вернём после сдачи' }}</dd></div>@endif
                @if($booking->promo_code)<div><dt>Промокод</dt><dd>{{ $booking->promo_code }}@if($booking->discount) — скидка {{ number_format($booking->discount, 0, ',', ' ') }} ₽ учтена@else — не подошёл к этой аренде@endif</dd></div>@endif
                <div class="thanks-total"><dt>Расчёт с сайта</dt><dd>{{ number_format($booking->total, 0, ',', ' ') }} ₽</dd></div>
                @if($booking->prepaid_amount)<div><dt>Оплачено онлайн</dt><dd>{{ number_format($booking->prepaid_amount, 0, ',', ' ') }} ₽</dd></div>
                <div><dt>При получении</dt><dd>{{ number_format(max(0, $booking->total - $booking->prepaid_amount), 0, ',', ' ') }} ₽</dd></div>@endif
            </dl>

            <div class="thanks-actions">
                <form method="post" action="{{ route('cabinet.from-booking', $booking->public_token) }}">@csrf<button class="btn btn-outline" type="submit">@include('partials.icon', ['name' => 'id-card', 'size' => 18]) Все мои брони</button></form>
                <a class="btn btn-secondary" href="{{ $calendarUrl }}">@include('partials.icon', ['name' => 'calendar', 'size' => 18]) Добавить в календарь</a>
                <a class="btn btn-outline" href="{{ route('catalog') }}">Вернуться в каталог</a>
            </div>
        </div>

        @if(in_array($stage, ['received', 'checking', 'confirmed'], true))
            @php($contact = array_keys(array_filter(['whatsapp' => $booking->whatsapp, 'telegram' => $booking->telegram, 'max' => $booking->max])))
            <form class="card card-pad thanks-details" id="details" method="post" action="{{ $detailsUrl }}">
                @csrf
                <h2>{{ Setting::get('booking_details_title') ?: 'Уточните детали' }} <span class="note">{{ Setting::get('booking_details_optional') ?: 'необязательно' }}</span></h2>
                @if(session('details_saved'))
                    <p class="details-saved" role="status">{{ Setting::get('booking_details_saved') ?: 'Спасибо, передали менеджеру.' }}
                        @if(($promo = session('promo_result')) && ! ($promo['ok'] ?? false)) {{ $promo['error'] ?? '' }} @endif
                    </p>
                    @if(($promo = session('promo_result')) && ($promo['ok'] ?? false))<span hidden data-analytics-goal="promo_applied"></span>@endif
                @else
                    <p class="note">{{ Setting::get('booking_details_text') ?: 'Чтобы менеджеру было проще: как к вам обращаться, где удобнее связаться и что добавить к аренде.' }}</p>
                @endif

                <div>
                    <label class="field-label" for="d-name">Как к вам обращаться</label>
                    <input class="field" id="d-name" type="text" name="customer_name" value="{{ old('customer_name', $booking->customer_name) }}" autocomplete="name" maxlength="120">
                </div>
                <fieldset>
                    <legend class="field-label">Как удобнее связаться</legend>
                    <div class="chips-check">
                        @foreach(['call' => 'Звонок', 'whatsapp' => 'WhatsApp', 'telegram' => 'Telegram', 'max' => 'MAX'] as $key => $label)
                            <label><input type="checkbox" name="contact[]" value="{{ $key }}" @checked(in_array($key, $contact, true))><span>{{ $label }}</span></label>
                        @endforeach
                    </div>
                </fieldset>
                @if($extrasList->isNotEmpty())
                    <fieldset class="extras">
                        <legend class="field-label">{{ Setting::get('extras_title') ?: 'Добавить к аренде' }}</legend>
                        <div class="extras-grid">
                            @foreach($extrasList as $extra)
                                <label class="check extra">
                                    <input type="checkbox" name="extras[]" value="{{ $extra->id }}" @checked(in_array($extra->id, array_column((array) $booking->extras, 'id')))>
                                    <span>{{ $extra->name }}</span>
                                    <small>{{ $extra->is_free || ! $extra->price_per_day ? 'бесплатно' : '+'.number_format($extra->price_per_day, 0, ',', ' ').' ₽/сут' }}@if($extra->waives_deposit) · залог 0 ₽@endif</small>
                                    @if($extra->description)<em class="extra-note">{{ $extra->description }}</em>@endif
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif
                <div>
                    <label class="field-label" for="d-promo">{{ Setting::get('promo_link') ?: 'Промокод' }}</label>
                    <input class="field" id="d-promo" type="text" name="promo_code" value="{{ old('promo_code', $booking->promo_code) }}" maxlength="40" autocomplete="off" autocapitalize="characters">
                </div>
                <button class="btn btn-primary" type="submit" data-submit data-busy-text="Сохраняем…">{{ Setting::get('booking_details_submit') ?: 'Сохранить' }}</button>
            </form>
        @endif

        @if(\App\Http\Controllers\BookingDocumentsController::acceptsDocuments($booking) && in_array($stage, ['received', 'checking', 'confirmed'], true))
            @php($uploaded = $booking->getMedia('documents')->map(fn ($m) => $m->getCustomProperty('type'))->all())
            <form class="card card-pad thanks-details" id="documents" method="post" action="{{ route('booking.documents', $booking->public_token) }}" enctype="multipart/form-data">
                @csrf
                <h2>{{ Setting::get('documents_title') ?: 'Документы заранее' }} <span class="note">{{ Setting::get('booking_details_optional') ?: 'необязательно' }}</span></h2>
                @if(session('documents_saved'))
                    <p class="details-saved" role="status">{{ Setting::get('documents_saved_text') ?: 'Получили. Договор подготовим заранее — на выдаче останется только подписать.' }}</p>
                @else
                    <p class="note">{{ Setting::get('documents_text') ?: 'Пришлите фото паспорта и прав — подготовим договор заранее, выдача займёт 5 минут. Файлы видит только менеджер, после аренды удаляем.' }}</p>
                @endif
                <div class="docs-grid">
                    @foreach(\App\Models\Booking::DOCUMENTS as $type => $label)
                        <label class="doc-slot @if(in_array($type, $uploaded, true)) is-done @endif">
                            <span class="doc-slot-label">{{ $label }}</span>
                            <span class="doc-slot-state">{{ in_array($type, $uploaded, true) ? '✓ получено — можно заменить' : 'Выбрать фото или PDF' }}</span>
                            <input type="file" name="docs[{{ $type }}]" accept="image/*,application/pdf">
                        </label>
                    @endforeach
                </div>
                @error('docs')<p class="field-error">{{ $message }}</p>@enderror
                @error('docs.*')<p class="field-error">{{ $message }}</p>@enderror
                <label class="check-consent"><input type="checkbox" name="documents_consent" value="1" required> <span>{{ Setting::get('documents_consent_text') ?: 'Согласен на обработку копий паспорта и водительского удостоверения для заключения договора аренды' }}</span></label>
                @error('documents_consent')<p class="field-error">{{ $message }}</p>@enderror
                <button class="btn btn-primary" type="submit" data-submit data-busy-text="Загружаем…">{{ Setting::get('documents_submit') ?: 'Отправить документы' }}</button>
            </form>
        @endif

        @php($actPickup = $booking->getMedia('act_pickup'))
        @php($actReturn = $booking->getMedia('act_return'))
        @if($actPickup->isNotEmpty() || $actReturn->isNotEmpty() || $booking->pickup_mileage)
            <section class="card card-pad thanks-details" id="act">
                <h2>{{ Setting::get('act_title') ?: 'Акт осмотра' }}</h2>
                <p class="note">{{ Setting::get('act_text') ?: 'Фото машины при выдаче и возврате — одинаковые для вас и для нас. Так залог возвращается без споров.' }}</p>
                @foreach([['Выдача', $actPickup, $booking->pickup_mileage, $booking->pickup_fuel], ['Возврат', $actReturn, $booking->return_mileage, $booking->return_fuel]] as [$title, $photos, $km, $fuel])
                    @if($photos->isNotEmpty() || $km)
                        <div>
                            <p class="field-label" style="margin:0 0 6px">{{ $title }}@if($km) · пробег {{ number_format($km, 0, ',', ' ') }} км @endif @if($fuel !== null) · топливо {{ $fuel }}% @endif</p>
                            <div class="act-photos">
                                @foreach($photos as $photo)
                                    <a href="{{ route('booking.act-photo', [$booking->public_token, $photo]) }}" target="_blank" rel="noopener"><img src="{{ route('booking.act-photo', [$booking->public_token, $photo]) }}" alt="{{ $title }}: фото {{ $loop->iteration }}" loading="lazy" width="120" height="90"></a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
                @if($booking->inspection_notes)<p class="note" style="white-space:pre-line">{{ $booking->inspection_notes }}</p>@endif
            </section>
        @endif

        <div class="card card-pad thanks-next">
            <h2>{{ Setting::get('thanks_steps_title') ?: 'Что дальше' }}</h2>
            <ol class="timeline">
                @foreach($steps as $i => $step)
                    <li @class(['is-current' => $i === 0])>
                        <span class="timeline-dot">{{ $i + 1 }}</span>
                        <div><b>{{ $step['title'] ?? '' }}</b>@if(!empty($step['text']))<p>{{ $step['text'] }}</p>@endif</div>
                    </li>
                @endforeach
            </ol>
            <p class="note" style="margin:16px 0 8px">Не хотите ждать звонка? Напишите нам — номер заявки подставится сам.</p>
            @include('partials.messengers', ['variant' => 'grid', 'text' => $msg])
        </div>
    </div>
</section>
@endsection
