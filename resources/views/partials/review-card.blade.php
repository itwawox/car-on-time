{{-- Отзыв: $review --}}
<article class="card card-pad review-card" @if($reveal ?? false) data-reveal @endif>
    <header class="review-head">
        <span class="review-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($review->author, 0, 1)) }}</span>
        <div>
            <p class="review-author">{{ $review->author }}@if($review->city)<span class="review-city">, {{ $review->city }}</span>@endif</p>
            <p class="review-meta">
                @include('partials.stars', ['value' => $review->rating, 'size' => 14])
                @if($review->reviewed_at)<time datetime="{{ $review->reviewed_at->toDateString() }}">{{ $review->reviewed_at->translatedFormat('j F Y') }}</time>@endif
            </p>
        </div>
    </header>
    <p class="review-body">{!! nl2br(e($review->body)) !!}</p>
    @if($review->car && ($showCar ?? true))
        <p class="review-car">@include('partials.icon', ['name' => 'car', 'size' => 14]) <a href="{{ route('car.show', $review->car->slug) }}">{{ $review->car->name }}</a></p>
    @endif
    @if($review->reply)
        <div class="review-reply">
            <p class="review-reply-title">{{ \App\Models\Setting::get('reviews_reply_label') ?: 'Ответ '.\App\Models\Setting::get('brand_name', 'Car on Time') }}</p>
            <p>{!! nl2br(e($review->reply)) !!}</p>
        </div>
    @endif
</article>
