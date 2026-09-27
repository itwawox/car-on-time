@extends('layouts.app', [
    'title' => \App\Support\Seo\SeoSettings::meta('quiz', 'title'),
    'description' => \App\Support\Seo\SeoSettings::meta('quiz', 'description'),
])

@section('content')
<section class="page-head">
    <div class="container-x">
        @include('partials.breadcrumbs', ['crumbs' => [
            ['name' => 'Главная', 'url' => route('home')],
            ['name' => 'Подбор авто'],
        ]])
        <h1>{{ \App\Support\Seo\SeoSettings::meta('quiz', 'h1') }}</h1>
        <p class="lead">{{ \App\Support\Seo\SeoSettings::meta('quiz', 'intro') }}</p>
    </div>
</section>

<section class="section" style="padding-top:20px">
    <div class="container-x page-layout">
        <form method="get" action="{{ route('quiz.result') }}" class="page-main card qz" data-quiz data-count-url="{{ route('quiz.count') }}" data-total="{{ $total }}" style="max-width:none">
            <div class="qz-top">
                <div class="qz-progress" aria-hidden="true"><span data-qz-bar></span></div>
                <div class="qz-meta">
                    <span data-qz-counter>Шаг 1 из {{ count($steps) }}</span>
                    <span class="qz-count" data-qz-count aria-live="polite">Подходит {{ $total }} авто</span>
                </div>
            </div>

            @foreach($steps as $i => $step)
                <fieldset class="qz-step" data-qz-step data-multiple="{{ $step['multiple'] ? 1 : 0 }}">
                    <legend class="qz-title"><span class="qz-num">{{ $i + 1 }}</span>{{ $step['title'] }}</legend>
                    <p class="qz-hint">{{ $step['hint'] }}</p>
                    <div class="qz-options qz-options-{{ count($step['options']) }}">
                        @foreach($step['options'] as $option)
                            @php($selected = in_array($option['value'], (array) ($preset[$step['name']] ?? []), true))
                            <label class="qz-option">
                                <input type="{{ $step['multiple'] ? 'checkbox' : 'radio' }}" name="{{ $step['name'] }}{{ $step['multiple'] ? '[]' : '' }}" value="{{ $option['value'] }}" @checked($selected) @if(!$step['multiple'] && $loop->first) required @endif>
                                <span class="qz-card">
                                    <span class="qz-icon">@include('partials.icon', ['name' => $option['icon'], 'size' => 26])</span>
                                    <span class="qz-label">{{ $option['label'] }}</span>
                                    <span class="qz-sub">{{ $option['hint'] }}</span>
                                    <span class="qz-check" aria-hidden="true">@include('partials.icon', ['name' => 'check', 'size' => 14, 'stroke' => 3])</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach

            <div class="qz-nav">
                <button type="button" class="btn btn-outline" data-qz-back hidden>@include('partials.icon', ['name' => 'chevron-left', 'size' => 18]) Назад</button>
                <button type="button" class="btn btn-primary" data-qz-next hidden>Далее @include('partials.icon', ['name' => 'arrow-right', 'size' => 18])</button>
                <button type="submit" class="btn btn-accent btn-lg" data-qz-submit>{{ \App\Models\Setting::get('quiz_submit') ?: 'Показать мои варианты' }} @include('partials.icon', ['name' => 'arrow-right', 'size' => 18])</button>
            </div>
        </form>
        @include('partials.help-aside')
    </div>
</section>
@endsection
