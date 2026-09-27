{{-- Форма отзыва. $car — машина по умолчанию (необязательно), $cars — список для выбора (необязательно) --}}
@php($S = \App\Models\Setting::class)
<div class="card card-pad review-form" id="review-form">
    <h2 style="font-size:1.25rem;margin:0 0 4px">{{ $S::get('reviews_form_title') ?: 'Оставить отзыв' }}</h2>
    <p class="note" style="margin:0 0 16px">{{ $S::get('reviews_form_text') ?: 'Отзыв появится на сайте после проверки. Телефон не публикуем — он нужен, только чтобы связаться, если что-то пошло не так.' }}</p>

    @if(session('review_sent'))
        <div class="review-thanks" role="status">
            @include('partials.icon', ['name' => 'check', 'size' => 18, 'stroke' => 2.4])
            <span>{{ $S::get('reviews_thanks') ?: 'Спасибо! Отзыв отправлен и появится на сайте после проверки.' }}</span>
        </div>
    @else
        <form method="post" action="{{ route('reviews.store') }}" class="field-group">
            @csrf
            <div class="rating-input" role="radiogroup" aria-label="Оценка">
                <span class="field-label">Оценка</span>
                <div class="rating-stars">
                    @for($i = 5; $i >= 1; $i--)
                        <input type="radio" id="rate-{{ $i }}" name="rating" value="{{ $i }}" @checked((int) old('rating', 5) === $i) required>
                        <label for="rate-{{ $i }}" title="{{ $i }} из 5"><span class="sr-only">{{ $i }} из 5</span>★</label>
                    @endfor
                </div>
                @error('rating')<p class="quote-error">{{ $message }}</p>@enderror
            </div>
            <div class="review-form-row">
                <div>
                    <label class="field-label" for="r-author">Имя</label>
                    <input class="field" id="r-author" name="author" value="{{ old('author') }}" required maxlength="80" autocomplete="given-name">
                    @error('author')<p class="quote-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="field-label" for="r-city">Город <span style="font-weight:400">(необязательно)</span></label>
                    <input class="field" id="r-city" name="city" value="{{ old('city') }}" maxlength="80" autocomplete="address-level2">
                </div>
            </div>
            @if(!empty($cars) && $cars->isNotEmpty())
                <div>
                    <label class="field-label" for="r-car">Автомобиль <span style="font-weight:400">(необязательно)</span></label>
                    <select class="field" id="r-car" name="car_id">
                        <option value="">— не важно —</option>
                        @foreach($cars as $option)
                            <option value="{{ $option->id }}" @selected((int) old('car_id', $car?->id) === $option->id)>{{ $option->name }}</option>
                        @endforeach
                    </select>
                </div>
            @elseif($car ?? null)
                <input type="hidden" name="car_id" value="{{ $car->id }}">
            @endif
            <div>
                <label class="field-label" for="r-body">Отзыв</label>
                <textarea class="field" id="r-body" name="body" rows="5" required minlength="20" maxlength="3000" placeholder="{{ $S::get('reviews_placeholder') ?: 'Как прошла выдача, какой была машина, что понравилось, а что стоит улучшить' }}">{{ old('body') }}</textarea>
                @error('body')<p class="quote-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="field-label" for="r-phone">Телефон <span style="font-weight:400">(не публикуется)</span></label>
                <input class="field" id="r-phone" type="tel" name="phone" value="{{ old('phone') }}" placeholder="+7" autocomplete="tel" inputmode="tel">
            </div>
            <div class="hp" aria-hidden="true"><label>Сайт <input name="website" tabindex="-1" autocomplete="off"></label></div>
            @include('partials.pd-consent', ['id' => 'r-consent', 'prefix' => 'Разрешаю публикацию отзыва, даю'])
            <button class="btn btn-primary btn-block" type="submit">{{ $S::get('reviews_submit') ?: 'Отправить отзыв' }}</button>
        </form>
    @endif
</div>
