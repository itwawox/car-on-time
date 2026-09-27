{{-- Заявка для компании (страница «Юрлицам») --}}
@php($S = \App\Models\Setting::class)
<div class="card card-pad" id="lead-form" style="margin-top:24px">
    <h2 style="margin:0 0 4px;font-size:1.375rem">{{ $S::get('corporate_form_title') ?: 'Заявка для компании' }}</h2>
    <p class="note" style="margin:0 0 16px">{{ $S::get('corporate_form_text') ?: 'Расскажите о задаче — подготовим предложение и договор. Обязателен только телефон.' }}</p>
    @if(session('lead_sent'))
        <div class="review-thanks" role="status">@include('partials.icon', ['name' => 'check', 'size' => 18, 'stroke' => 2.4]) <span>{{ $S::get('lead_thanks') ?: 'Спасибо! Свяжемся с вами в ближайшее время.' }}</span></div>
    @else
        <form method="post" action="{{ route('lead.store') }}" class="field-group">
            @csrf
            <input type="hidden" name="type" value="corporate">
            <div class="review-form-row">
                <div><label class="field-label" for="l-company">Компания</label><input class="field" id="l-company" name="company" value="{{ old('company') }}" maxlength="160" autocomplete="organization"></div>
                <div><label class="field-label" for="l-inn">ИНН <span style="font-weight:400">(необязательно)</span></label><input class="field" id="l-inn" name="inn" value="{{ old('inn') }}" maxlength="20" inputmode="numeric"></div>
            </div>
            <div class="review-form-row">
                <div><label class="field-label" for="l-name">Контактное лицо</label><input class="field" id="l-name" name="name" value="{{ old('name') }}" maxlength="120" autocomplete="name"></div>
                <div>
                    <label class="field-label" for="l-phone">Телефон</label>
                    <input class="field" id="l-phone" type="tel" name="phone" value="{{ old('phone') }}" required placeholder="+7 (___) ___-__-__" autocomplete="tel" inputmode="tel" data-phone-mask aria-describedby="l-phone-error" @error('phone') aria-invalid="true" @enderror>
                    <p class="field-error" id="l-phone-error">@error('phone'){{ $message }}@enderror</p>
                </div>
            </div>
            <div><label class="field-label" for="l-msg">Задача</label><textarea class="field" id="l-msg" name="message" rows="4" maxlength="2000" placeholder="Сколько машин, на какой срок, для кого — сотрудники, гости, мероприятие">{{ old('message') }}</textarea></div>
            <div class="hp" aria-hidden="true"><label>Сайт <input name="website" tabindex="-1" autocomplete="off"></label></div>
            @include('partials.pd-consent', ['id' => 'l-consent'])
            <button class="btn btn-primary" type="submit" data-submit data-busy-text="Отправляем…">Отправить заявку</button>
        </form>
    @endif
</div>
