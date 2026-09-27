@if ($paginator->hasPages())
    <nav class="pager" aria-label="Страницы каталога">
        @if ($paginator->onFirstPage())
            <span class="pager-btn is-disabled" aria-disabled="true"><span class="rotate-180 inline-flex">@include('partials.icon', ['name' => 'arrow-right', 'size' => 16])</span><span class="pager-label">Назад</span></span>
        @else
            <a class="pager-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Предыдущая страница"><span class="rotate-180 inline-flex">@include('partials.icon', ['name' => 'arrow-right', 'size' => 16])</span><span class="pager-label">Назад</span></a>
        @endif

        <ul class="pager-pages">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="pager-gap">…</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page == $paginator->currentPage())
                                <span class="pager-btn is-current" aria-current="page">{{ $page }}</span>
                            @else
                                <a class="pager-btn" href="{{ $url }}" aria-label="Страница {{ $page }}">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach
        </ul>

        @if ($paginator->hasMorePages())
            <a class="pager-btn" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Следующая страница"><span class="pager-label">Вперёд</span>@include('partials.icon', ['name' => 'arrow-right', 'size' => 16])</a>
        @else
            <span class="pager-btn is-disabled" aria-disabled="true"><span class="pager-label">Вперёд</span>@include('partials.icon', ['name' => 'arrow-right', 'size' => 16])</span>
        @endif
    </nav>
@endif
