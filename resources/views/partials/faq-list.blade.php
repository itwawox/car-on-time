@if(isset($faqs) && $faqs->isNotEmpty())
<div class="faq" @isset($reveal) data-reveal @endisset>
    @foreach($faqs as $faq)
        @php($anchor = method_exists($faq, 'anchor') ? $faq->anchor() : null)
        <details @if($anchor) id="{{ $anchor }}" @endif @if($loop->first && !empty($openFirst)) open @endif data-faq-item>
            <summary>
                <span>{{ $faq->question }}</span>
                <span class="faq-icon">@include('partials.icon', ['name' => 'plus', 'size' => 16, 'stroke' => 2.2])</span>
            </summary>
            <div class="faq-answer">
                @foreach(preg_split('/\R+/u', trim((string) $faq->answer)) as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
                @if(!empty($faq->link_url) || $anchor)
                    <div class="faq-actions">
                        @if(!empty($faq->link_url))
                            <a class="link-arrow" href="{{ $faq->link_url }}">{{ $faq->link_label ?: 'Подробнее' }} @include('partials.icon', ['name' => 'arrow-right', 'size' => 16])</a>
                        @endif
                        @if($anchor && !empty($copyLinks))
                            <button type="button" class="faq-copy" data-copy-link="#{{ $anchor }}">@include('partials.icon', ['name' => 'share', 'size' => 14]) Ссылка на вопрос</button>
                        @endif
                    </div>
                @endif
            </div>
        </details>
    @endforeach
</div>
@endif
