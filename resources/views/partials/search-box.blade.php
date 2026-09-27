@php
    $id = $id ?? 'search';
    $variant = $variant ?? 'inline';
    $asForm = $asForm ?? true;
    $tag = $asForm ? 'form' : 'div';
@endphp
<{{ $tag }} class="sbox sbox-{{ $variant }}"
    data-search-box
    data-suggest-url="{{ route('search.suggest') }}"
    data-search-url="{{ route('search') }}"
    @if($asForm) action="{{ route('search') }}" method="get" role="search" @endif>
    <div class="sbox-field">
        <span class="sbox-icon">@include('partials.icon', ['name' => 'search', 'size' => 20])</span>
        @if(!empty($label))
            <label class="sr-only" for="{{ $id }}-input">{{ $label }}</label>
        @endif
        <input class="sbox-input" id="{{ $id }}-input" type="search" name="q"
               value="{{ $value ?? '' }}"
               placeholder="{{ $placeholder ?? \App\Support\Search\SearchSettings::text('placeholder') }}"
               role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="{{ $id }}-list"
               autocomplete="off" autocapitalize="off" spellcheck="false" enterkeyhint="search" maxlength="100"
               @if(!empty($autofocus)) autofocus @endif>
        <button class="sbox-clear" type="button" data-search-clear aria-label="Очистить" hidden>@include('partials.icon', ['name' => 'close', 'size' => 16])</button>
        @if($variant === 'palette')
            <kbd class="sbox-kbd" data-search-close>Esc</kbd>
        @elseif($asForm)
            <button class="btn btn-primary btn-sm sbox-submit" type="submit">{{ \App\Support\Search\SearchSettings::text('submit') }}</button>
        @endif
    </div>
    <div class="sbox-panel" id="{{ $id }}-list" role="listbox" aria-label="Подсказки поиска" hidden></div>
</{{ $tag }}>
