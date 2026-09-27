@extends('layouts.app')

@section('content')
<section class="page-head">
    <div class="container-x">
        @include('partials.breadcrumbs')
        <h1>{{ \App\Support\Seo\SeoSettings::meta('reviews', 'h1') }}</h1>
        @if($intro = \App\Support\Seo\SeoSettings::meta('reviews', 'intro'))<p class="hero-lead" style="color:var(--color-muted);max-width:720px">{{ $intro }}</p>@endif
    </div>
</section>

<section class="section" style="padding-top:24px">
    <div class="container-x page-layout">
        <div class="page-main" style="max-width:none">
            @if($summary['count'] > 0)
                <div class="card card-pad reviews-summary">
                    <span class="reviews-summary-value">{{ number_format($summary['avg'], 1, ',', '') }}</span>
                    <div>
                        @include('partials.stars', ['value' => $summary['avg'], 'size' => 20])
                        <p class="note" style="margin:4px 0 0">{{ $summary['count'] }} {{ trans_choice('отзыв|отзыва|отзывов', $summary['count']) }} на сайте</p>
                    </div>
                    <a class="btn btn-accent" href="#review-form" style="margin-left:auto">{{ \App\Models\Setting::get('reviews_form_title') ?: 'Оставить отзыв' }}</a>
                </div>
                <div class="reviews-list">
                    @foreach($reviews as $review)
                        @include('partials.review-card', ['review' => $review, 'reveal' => true])
                    @endforeach
                </div>
                {{ $reviews->onEachSide(1)->links() }}
            @else
                <div class="card card-pad" style="text-align:center">
                    <p style="margin:0;font-weight:600">{{ \App\Models\Setting::get('reviews_empty_title') ?: 'Здесь пока нет отзывов' }}</p>
                    <p class="note" style="margin:6px 0 0">{{ \App\Models\Setting::get('reviews_empty_text') ?: 'Брали у нас машину? Расскажите, как прошла поездка, — ваш отзыв будет первым.' }}</p>
                </div>
            @endif

            <div style="margin-top:24px">
                @include('partials.review-form', ['car' => $car, 'cars' => $cars])
            </div>
        </div>
        @include('partials.help-aside')
    </div>
</section>
@endsection
