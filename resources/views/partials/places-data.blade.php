@once
    {{-- Точки выдачи для выбора с поиском (place-picker.js) --}}
    <script type="application/json" id="places-data">{!! json_encode(array_map(fn ($p) => array_intersect_key($p, array_flip(['id', 'group', 'label', 'price', 'popular'])), \App\Support\Places::all()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endonce
