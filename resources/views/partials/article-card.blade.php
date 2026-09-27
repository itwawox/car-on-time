<article class="card card-link relative flex flex-col overflow-hidden" @isset($reveal) data-reveal @endisset>
    <a class="article-card-media" data-skeleton href="{{ $article->url() }}" tabindex="-1" aria-hidden="true">
        @if($cover = $article->coverUrl('600'))
            <img src="{{ $cover }}" alt="" width="600" height="338" loading="lazy" decoding="async">
        @else
            <span class="grid h-full place-items-center text-sea-100">@include('partials.icon', ['name' => 'pin', 'size' => 44, 'stroke' => 1.4])</span>
        @endif
    </a>
    <div class="flex flex-1 flex-col gap-2" style="padding:18px 20px 20px">
        @if($article->category)<span class="eyebrow" style="align-self:flex-start;padding:3px 10px;font-size:.75rem">{{ $article->category }}</span>@endif
        <h2 class="car-card-title" style="font-size:1.125rem;line-height:1.35"><a href="{{ $article->url() }}">{{ $article->title }}</a></h2>
        @if($article->excerpt)<p class="note" style="margin:0;font-size:.875rem">{{ \Illuminate\Support\Str::limit($article->excerpt, 150) }}</p>@endif
        <p class="note" style="margin:auto 0 0;padding-top:8px">
            @if($article->published_at)<time datetime="{{ $article->published_at->toDateString() }}">{{ $article->published_at->translatedFormat('j F Y') }}</time> · @endif
            {{ $article->readingMinutes() }} мин чтения
        </p>
    </div>
</article>
