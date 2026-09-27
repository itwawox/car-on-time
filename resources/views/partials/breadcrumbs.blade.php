@if(!empty($crumbs))
<nav class="crumbs" aria-label="Хлебные крошки">
    @foreach($crumbs as $i => $crumb)
        @if($i > 0)@include('partials.icon', ['name' => 'chevron-right', 'size' => 14])@endif
        @if(!empty($crumb['url']) && !$loop->last)
            <a href="{{ $crumb['url'] }}">{{ $crumb['name'] }}</a>
        @else
            <span aria-current="page">{{ $crumb['name'] }}</span>
        @endif
    @endforeach
</nav>
@endif
