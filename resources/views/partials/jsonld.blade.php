@if(!empty($jsonld))
    @foreach((array) $jsonld as $block)
        @if($block)
            <script type="application/ld+json">{!! json_encode($block, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>
        @endif
    @endforeach
@endif
