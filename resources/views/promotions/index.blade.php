@extends('layouts.app')

@section('content')
<section class="page-head">
    <div class="container-x">
        @include('partials.breadcrumbs')
        <h1>{{ $heading }}</h1>
        <p style="color:var(--color-muted);max-width:720px;margin:8px 0 0">{{ \App\Models\Setting::get('promotions_intro') ?: 'Назовите промокод в заявке или при звонке — скидку подтвердит менеджер.' }}</p>
    </div>
</section>
<section class="section" style="padding-top:24px">
    <div class="container-x">
        @if($promotions->isEmpty())
            <div class="card card-pad" style="text-align:center">
                <p style="margin:0;font-weight:600">Сейчас акций нет</p>
                <p class="note" style="margin:6px 0 16px">Загляните позже или спросите у менеджера — подскажем, как сэкономить на длительной аренде.</p>
                <a class="btn btn-primary" href="{{ route('catalog') }}">Перейти в каталог</a>
            </div>
        @else
            <div class="promo-grid">
                @foreach($promotions as $promotion)
                    @include('partials.promo-card', ['promotion' => $promotion])
                @endforeach
            </div>
        @endif
    </div>
</section>
@endsection
