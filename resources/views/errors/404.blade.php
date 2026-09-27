@extends('layouts.app', [
    'title' => 'Страница не найдена | '.\App\Models\Setting::get('brand_name', 'Car on Time'),
    'description' => null,
    'noindex' => true,
    'canonical' => url()->current(),
])

@section('content')
<section class="section">
    <div class="container-x" style="max-width:720px;text-align:center">
        <p class="eyebrow" style="margin-bottom:16px">Ошибка 404</p>
        <h1 style="margin:0 0 12px">Такой страницы нет</h1>
        <p class="text-muted" style="margin:0 auto 28px;max-width:46ch">Возможно, машину сняли с аренды или адрес набран с ошибкой. Найдите нужное через поиск или загляните в каталог.</p>
        <div style="text-align:left;margin-bottom:24px">
            @include('partials.search-box', ['id' => 'notfound', 'label' => 'Поиск по каталогу'])
        </div>
        <div class="flex flex-wrap justify-center gap-2">
            <a class="btn btn-primary" href="{{ route('catalog') }}">Весь каталог</a>
            <a class="btn btn-outline" href="{{ route('home') }}">На главную</a>
            <a class="btn btn-outline" href="tel:{{ \App\Models\Setting::get('phone_raw') }}">{{ \App\Models\Setting::get('phone') }}</a>
        </div>
    </div>
</section>
@endsection
