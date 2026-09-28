{{-- «Как это работает» — 4 шага. $compact — в карточке машины. --}}
@php($steps = \App\Support\Trust::steps())
@if($steps)
    <section class="{{ ($compact ?? false) ? 'steps steps-compact' : 'section section-tight' }}" aria-labelledby="h-steps-{{ ($compact ?? false) ? 'c' : 'h' }}">
        <div class="{{ ($compact ?? false) ? '' : 'container-x' }}">
            <h2 id="h-steps-{{ ($compact ?? false) ? 'c' : 'h' }}" @class(['steps-title' => $compact ?? false]) @if(!($compact ?? false)) data-reveal @endif>{{ \App\Models\Setting::get('trust_steps_title') ?: 'Как это работает' }}</h2>
            <ol @class(['steps-list', 'steps-flow' => ! ($compact ?? false)]) @if(!($compact ?? false)) data-steps-flow @endif>
                @foreach($steps as $i => $step)
                    <li class="step" style="--i: {{ $i }}@if(!($compact ?? false)); --reveal-delay: {{ $i * 80 }}ms @endif" @if(!($compact ?? false)) data-reveal @endif>
                        <span class="step-icon">@include('partials.icon', ['name' => $step['icon'], 'size' => ($compact ?? false) ? 16 : 20])<span class="step-num">{{ $i + 1 }}</span></span>
                        <div><b>{{ $step['title'] }}</b>@if($step['text'])<span>{{ $step['text'] }}</span>@endif</div>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>
@endif
