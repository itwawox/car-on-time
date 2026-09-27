{{-- Заявка владельца (страница «Сдать авто»). Обязателен только телефон. --}}
@php($S = \App\Models\Setting::class)
<div class="card card-pad" id="lead-form" style="margin-top:24px">
    <h2 style="margin:0 0 4px;font-size:1.375rem">{{ $S::get('owner_form_title') ?: 'Заявка владельца' }}</h2>
    <p class="note" style="margin:0 0 16px">{{ $S::get('owner_form_text') ?: 'Расскажите о машине — перезвоним, ответим на вопросы и договоримся об осмотре. Обязателен только телефон.' }}</p>
    @if(session('lead_sent'))
        <div class="review-thanks" role="status">@include('partials.icon', ['name' => 'check', 'size' => 18, 'stroke' => 2.4]) <span>{{ $S::get('owner_thanks') ?: $S::get('lead_thanks') ?: 'Спасибо! Свяжемся с вами в ближайшее время.' }}</span></div>
    @else
        <form method="post" action="{{ route('lead.store') }}" class="field-group">
            @csrf
            <input type="hidden" name="type" value="owner">
            <div class="review-form-row">
                <div><label class="field-label" for="o-name">Имя</label><input class="field" id="o-name" name="name" value="{{ old('name') }}" maxlength="120" autocomplete="name"></div>
                <div>
                    <label class="field-label" for="o-phone">Телефон</label>
                    <input class="field" id="o-phone" type="tel" name="phone" value="{{ old('phone') }}" required placeholder="+7 (___) ___-__-__" autocomplete="tel" inputmode="tel" data-phone-mask aria-describedby="o-phone-error" @error('phone') aria-invalid="true" @enderror>
                    <p class="field-error" id="o-phone-error">@error('phone'){{ $message }}@enderror</p>
                </div>
            </div>
            <div class="review-form-row">
                <div>
                    <label class="field-label" for="o-kind">Вы</label>
                    <select class="field" id="o-kind" name="details[owner_kind]">
                        <option value="">Не указано</option>
                        @foreach(\App\Models\Lead::OWNER_KINDS as $value => $label)
                            <option value="{{ $value }}" @selected(old('details.owner_kind') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label" for="o-city">Город</label>
                    <input class="field" id="o-city" name="details[city]" value="{{ old('details.city') }}" maxlength="80" list="o-cities" autocomplete="address-level2">
                    <datalist id="o-cities">
                        @foreach(\App\Models\City::query()->published()->orderBy('name')->pluck('name') as $cityName)
                            <option value="{{ $cityName }}">
                        @endforeach
                    </datalist>
                </div>
            </div>
            <div class="review-form-row">
                <div><label class="field-label" for="o-car">Марка и модель</label><input class="field" id="o-car" name="details[car]" value="{{ old('details.car') }}" maxlength="120" placeholder="Например, Kia Rio"></div>
                <div>
                    <label class="field-label" for="o-year">Год выпуска</label>
                    <input class="field" id="o-year" type="number" name="details[year]" value="{{ old('details.year') }}" min="1990" max="{{ now()->year + 1 }}" inputmode="numeric" aria-describedby="o-year-error" @error('details.year') aria-invalid="true" @enderror>
                    <p class="field-error" id="o-year-error">@error('details.year'){{ $message }}@enderror</p>
                </div>
            </div>
            <div class="review-form-row">
                <div>
                    <label class="field-label" for="o-kp">Коробка</label>
                    <select class="field" id="o-kp" name="details[gearbox]">
                        <option value="">Не указано</option>
                        <option value="at" @selected(old('details.gearbox') === 'at')>Автомат</option>
                        <option value="mt" @selected(old('details.gearbox') === 'mt')>Механика</option>
                    </select>
                </div>
                <div><label class="field-label" for="o-count">Сколько машин</label><input class="field" id="o-count" type="number" name="details[cars_count]" value="{{ old('details.cars_count', 1) }}" min="1" max="500" inputmode="numeric"></div>
            </div>
            <div><label class="field-label" for="o-msg">Комментарий</label><textarea class="field" id="o-msg" name="message" rows="3" maxlength="2000" placeholder="Когда машина свободна, пробег, особенности">{{ old('message') }}</textarea></div>
            <div class="hp" aria-hidden="true"><label>Сайт <input name="website" tabindex="-1" autocomplete="off"></label></div>
            @include('partials.pd-consent', ['id' => 'o-consent'])
            <button class="btn btn-primary" type="submit" data-submit data-busy-text="Отправляем…">Отправить заявку</button>
        </form>
    @endif
</div>
