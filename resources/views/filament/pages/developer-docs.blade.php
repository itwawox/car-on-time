@php
    $sections = $this->sections();
    $current = $this->current();
    $around = $this->neighbours();
@endphp
<x-filament-panels::page>
    <style>
        .dev-docs { display: grid; grid-template-columns: 260px minmax(0, 1fr); gap: 24px; align-items: start; }
        .dev-docs-nav { position: sticky; top: 80px; display: flex; flex-direction: column; gap: 2px; }
        .dev-docs-nav a { display: flex; gap: 8px; padding: 8px 12px; border-radius: 8px; font-size: .875rem; line-height: 1.3; color: inherit; }
        .dev-docs-nav a:hover { background: rgba(127, 127, 127, .1); }
        .dev-docs-nav a.is-active { background: var(--primary-50); color: var(--primary-700); font-weight: 600; }
        .dark .dev-docs-nav a.is-active { background: rgba(255, 255, 255, .08); color: var(--primary-300); }
        .dev-docs-nav span { opacity: .5; min-width: 1.4em; }
        .dev-docs-body { max-width: 860px; }
        .dev-docs-body table { display: block; overflow-x: auto; font-size: .875rem; }
        .dev-docs-body pre { white-space: pre; overflow-x: auto; padding: 14px 16px; border-radius: 10px; background: rgba(127, 127, 127, .12); font-size: .8125rem; line-height: 1.6; }
        .dev-docs-body code::before, .dev-docs-body code::after { content: none !important; }
        .dev-docs-body :not(pre) > code { padding: 1px 6px; border-radius: 6px; background: rgba(127, 127, 127, .14); font-size: .85em; font-weight: 500; white-space: nowrap; }
        .dev-docs-body blockquote { border-left: 4px solid var(--warning-500); padding: 4px 16px; background: rgba(244, 162, 97, .08); border-radius: 0 8px 8px 0; }
        .dev-docs-search { margin-bottom: 8px; }
        .dev-docs-results { display: grid; gap: 4px; margin: 0; padding: 0; list-style: none; }
        .dev-docs-results a { display: block; padding: 12px 14px; border-radius: 10px; color: inherit; }
        .dev-docs-results a:hover { background: rgba(127, 127, 127, .1); }
        .dev-docs-results small { display: block; font-size: .75rem; opacity: .6; }
        .dev-docs-results strong { display: block; margin: 2px 0 4px; }
        .dev-docs-results p { margin: 0; font-size: .875rem; line-height: 1.5; opacity: .85; }
        .dev-docs-results mark { background: rgba(250, 204, 21, .35); color: inherit; border-radius: 3px; padding: 0 1px; }
        .dev-docs-pager { display: flex; justify-content: space-between; gap: 12px; margin-top: 32px; padding-top: 16px; border-top: 1px solid rgba(127, 127, 127, .2); font-size: .875rem; }
        @media (max-width: 1024px) { .dev-docs { grid-template-columns: 1fr; } .dev-docs-nav { position: static; } }
    </style>

    <div class="dev-docs">
        <nav class="dev-docs-nav" aria-label="Разделы">
            <div class="dev-docs-search">
                <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                    <x-filament::input type="search" wire:model.live.debounce.300ms="q" placeholder="Поиск по курсу и документации" aria-label="Поиск по курсу и документации" />
                </x-filament::input.wrapper>
            </div>
            @foreach($sections->values() as $i => $item)
                <a href="?section={{ $item['slug'] }}" wire:navigate @class(['is-active' => $item['slug'] === $current['slug']])>
                    <span>{{ $i + 1 }}.</span>{{ $item['title'] }}
                </a>
            @endforeach
        </nav>

        @if(filled(trim($q)))
            @php($results = $this->searchResults())
            <x-filament::section :heading="$results ? 'Нашлось: '.count($results) : 'Ничего не нашлось'" :description="$results ? null : 'Попробуйте другое слово или короче: «тест», «миграц», «деплой».'">
                <ul class="dev-docs-results">
                    @foreach($results as $result)
                        <li>
                            <a href="{{ $result['url'] }}" wire:navigate>
                                <small>{{ $result['book'] }} · {{ $result['title'] }}</small>
                                <strong>{{ $result['heading'] ?? $result['title'] }}</strong>
                                <p>{{ $result['snippet'] }}</p>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </x-filament::section>
        @else
        <x-filament::section>
            <article class="fi-prose dev-docs-body">
                {{ $this->html() }}
            </article>

            <div class="dev-docs-pager">
                <div>@if($around['prev'])<a href="?section={{ $around['prev']['slug'] }}" wire:navigate>← {{ $around['prev']['title'] }}</a>@endif</div>
                <div>@if($around['next'])<a href="?section={{ $around['next']['slug'] }}" wire:navigate>{{ $around['next']['title'] }} →</a>@endif</div>
            </div>
        </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
