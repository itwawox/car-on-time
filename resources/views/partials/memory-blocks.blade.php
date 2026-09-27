{{-- Персональные блоки: заполняются в браузере из localStorage (memory.js). Без истории просмотров — скрыты. --}}
@php($S = \App\Models\Setting::class)
<div class="memory" data-memory data-exclude="{{ $exclude ?? '' }}"
     data-similar="{{ ($similar ?? false) ? route('quiz.similar') : '' }}"
     data-resume="{{ ($resume ?? false) ? 1 : 0 }}">
    @if($welcome ?? false)
        {{-- Приветствие вернувшегося: заполняется из localStorage (даты, просмотры) --}}
        <div class="welcome" data-welcome hidden>
            <span class="welcome-icon" aria-hidden="true">@include('partials.icon', ['name' => 'calendar', 'size' => 18])</span>
            <p><b>{{ $S::get('welcome_title') ?: 'С возвращением!' }}</b> <span data-welcome-text></span></p>
            <a class="btn btn-primary btn-sm" data-welcome-link href="{{ route('catalog') }}"></a>
            <button type="button" class="dismiss" data-dismiss aria-label="Закрыть" title="Закрыть">@include('partials.icon', ['name' => 'close', 'size' => 16, 'stroke' => 2.2])</button>
        </div>
    @endif
    <div class="quiz-resume" data-quiz-resume hidden>
        <a class="quiz-resume-link" data-quiz-resume-link href="#">
            @include('partials.icon', ['name' => 'check', 'size' => 16, 'stroke' => 2.2])
            <span><b>{{ $S::get('quiz_resume_text') ?: 'Продолжить подбор' }}:</b> <span data-quiz-resume-text></span></span>
            @include('partials.icon', ['name' => 'arrow-right', 'size' => 16])
        </a>
        <button type="button" class="dismiss" data-dismiss aria-label="Закрыть" title="Закрыть">@include('partials.icon', ['name' => 'close', 'size' => 16, 'stroke' => 2.2])</button>
    </div>
    <section class="memory-block" data-recent hidden aria-labelledby="h-recent-{{ $id ?? 'm' }}">
        <div class="memory-head">
            <h2 id="h-recent-{{ $id ?? 'm' }}">{{ $S::get('recent_title') ?: 'Вы смотрели' }}</h2>
            <button type="button" class="memory-clear" data-recent-clear>Очистить</button>
        </div>
        <div class="memory-row" data-recent-list data-rail></div>
    </section>
    @if($similar ?? false)
        <section class="memory-block" data-similar-block hidden aria-labelledby="h-similar-{{ $id ?? 'm' }}">
            <div class="memory-head"><h2 id="h-similar-{{ $id ?? 'm' }}">{{ $S::get('similar_title') ?: 'Похоже на то, что вы смотрели' }}</h2></div>
            <div class="memory-row" data-similar-list data-rail></div>
        </section>
    @endif
</div>
