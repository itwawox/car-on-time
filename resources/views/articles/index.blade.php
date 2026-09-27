@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
    'noindex' => $noindex,
    'canonical' => $canonical,
    'jsonld' => $jsonld,
])

@section('content')
<section class="page-head">
    <div class="container-x">
        @include('partials.breadcrumbs')
        <h1>{{ $h1 }}</h1>
        @if($intro && $articles->onFirstPage())<p class="lead">{{ $intro }}</p>@endif
    </div>
</section>

<section class="section" style="padding-top:24px">
    <div class="container-x">
        @if($categories->count() > 1)
            <nav class="filter-bar" data-rail aria-label="Рубрики" style="margin-bottom:24px">
                <a class="filter" href="{{ route('articles') }}" @if(!request('rubrika')) aria-current="page" @endif>Все</a>
                @foreach($categories as $category)
                    <a class="filter" href="{{ route('articles', ['rubrika' => $category]) }}" rel="nofollow" @if(request('rubrika') === $category) aria-current="page" @endif>{{ $category }}</a>
                @endforeach
            </nav>
        @endif

        @if($articles->isEmpty())
            <div class="card card-pad" style="max-width:640px">
                <p class="m-0 font-semibold">Скоро здесь появятся статьи.</p>
                <p class="note" style="margin:8px 0 16px">А пока — подберите машину в каталоге.</p>
                <a class="btn btn-primary" href="{{ route('catalog') }}">В каталог</a>
            </div>
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3" data-reveal-group>
                @foreach($articles as $article)
                    @include('partials.article-card', ['article' => $article, 'reveal' => true])
                @endforeach
            </div>
            {{ $articles->onEachSide(1)->links() }}
        @endif
    </div>
</section>
@endsection

@push('head')
    @if (! $articles->onFirstPage())<link rel="prev" href="{{ $articles->previousPageUrl() }}">@endif
    @if ($articles->hasMorePages())<link rel="next" href="{{ $articles->nextPageUrl() }}">@endif
@endpush
